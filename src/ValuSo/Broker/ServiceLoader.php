<?php
namespace ValuSo\Broker;

use	SplObjectStorage;
use Traversable;
use	ValuSo\Exception;
use	ValuSo\Feature;
use ValuSo\Invoker\DefinitionBased;
use ValuSo\Command\CommandManager;
use Zend\Cache\Storage\StorageInterface;
use	Zend\Loader\PluginClassLoader;
use Zend\EventManager\EventManagerInterface;
use	Zend\ServiceManager\ServiceLocatorInterface;
use	Zend\ServiceManager\ServiceLocatorAwareInterface;
use	Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Service\ServiceManagerConfig;

class ServiceLoader{
	
	/**
	 * Registered service
	 * 
	 * @var PriorityQueue
	 */
	private $services;
	
	/**
	 * Array that maps service IDs to
	 * corresponding service names
	 * 
	 * @var array
	 */
	private $serviceNames;
	
	/**
	 * Contains un-attached service IDs
	 * 
	 * @var array
	 */
	private $unAttached;
	
	/**
	 * Service plugin manager
	 * 
	 * @var \Valu\Service\ServicePluginManager
	 */
	private $servicePluginManager = null;

	/**
	 * Invoker
	 * 
	 * @var InvokerInterface
	 */
	private $invoker;
	
	/**
	 * Cache adapter
	 *
	 * @var \Zend\Cache\Storage\StorageInterface
	 */
	private $cache;
	
	/**
	 * PCRE pattern for ID matching
	 * 
	 * @var string
	 */
	private $validIdPattern = '/^[a-z]+[a-z0-9]*/i';
	
	/**
	 * PCRE pattern for name matching
	 *
	 * @var string
	 */
	private $validNamePattern = '/^[a-z]+(\.?[0-9a-z]+)*$/i';
	
	public function __construct($options = null)
	{
		$this->services = array();
		$this->serviceNames = array();
		
		if(null !== $options){
			$this->setOptions($options);
		}
	}
	
	/**
	 * Configure service loader
	 * 
	 * @param array|\Traversable $options
	 * @throws \InvalidArgumentException
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function setOptions($options)
	{
		if (!is_array($options) && !$options instanceof Traversable) {
			throw new \InvalidArgumentException(sprintf(
				'Expected an array or Traversable; received "%s"',
				(is_object($options) ? get_class($options) : gettype($options))
			));
		}
		
		foreach ($options as $key => $value){
			
			$key = strtolower($key);
			
			switch ($key) {
			    case 'services':
			        $this->registerServices($value);
			        break;
			    case 'locator':
			    case 'service_locator':
			        $this->setServiceLocator($value);
			        break;
		        case 'service_manager':
		            $this->configureServiceManager($value);
		            break;
			    case 'cache':
			        $this->setCache($value);
			        break;
			}
		}
		
		return $this;
	}
	
	/**
	 * Configure internal service manager
	 * 
	 * @param ServiceManagerConfig $configuration
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function configureServiceManager(ServiceManagerConfig $configuration)
	{
	    $configuration->configureServiceManager(
            $this->getServicePluginManager());
	    
	    return $this; 
	}
	
	/**
	 * @see \Zend\ServiceManager\ServiceManager::addPeeringServiceManager()
	 */
	public function addPeeringServiceManager(ServiceManager $manager)
	{
	    $this->getServicePluginManager()
	         ->addPeeringServiceManager($manager);
	    return $this;
	}
	
	/**
	 * @see \Zend\ServiceManager\ServiceManager::addInitializer()
	 */
	public function addInitializer($initializer, $topOfStack = true)
	{
	    $this->getServicePluginManager()
	         ->addInitializer($initializer, $topOfStack = true);
	    return $this;
	}
	
	/**
	 * Set main service locator so factories can have access to
	 * it when building services
	 * 
	 * @param ServiceLocatorInterface $locator
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function setServiceLocator(ServiceLocatorInterface $locator)
	{
	    $this->getServicePluginManager()->setServiceLocator($locator);
	    return $this;
	}
	
	/**
	 * Retrieve main service locator
	 * 
	 * @return ServiceLocatorInterface
	 */
	public function getServiceLocator()
	{
	    return $this->getServicePluginManager()->getServiceLocator();
	}
	
	/**
	 * Batch register services
	 * 
	 * @param array|\Traversable $services
	 * @throws \InvalidArgumentException
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function registerServices($services)
	{
	    
	    if (!is_array($services) && !($services instanceof Traversable)) {
	        throw new Exception\InvalidArgumentException(sprintf(
                'Expected an array or Traversable; received "%s"',
                (is_object($services) ? get_class($services) : gettype($services))
	        ));
	    }
	    
		foreach($services as $key => $impl){
		    
		    if (is_string($impl)) {
		        $impl = ['name' => $impl];
		    }
		    
		    $enabled = isset($impl['enabled']) ? $impl['enabled'] : true;
		    
		    if (!$enabled){
		        continue;
		    }
		    
			$id 		= isset($impl['id']) ? $impl['id'] : $key;
			$name 	    = isset($impl['name']) ? $impl['name'] : null;
			$service 	= isset($impl['service']) ? $impl['service'] : null;
			$factory 	= isset($impl['factory']) ? $impl['factory'] : null;
			$options 	= isset($impl['options']) ? $impl['options'] : null;
			$priority 	= isset($impl['priority']) ? $impl['priority'] : 1;
			
			if (!$service && isset($impl['class'])) {
			    $service = $impl['class'];
			}

			if(is_null($options) && isset($impl['config'])){
			    $options = $impl['config'];
			}
			
			if(!$name){
				throw new Exception\InvalidArgumentException('Service name is not defined for service: '.$id);
			}
			
			$this->registerService($id, $name, $service, $options, $priority);
			
			if($factory){
			    $this->getServicePluginManager()->setFactory($id, $factory);
			}
		}
		
		return $this;
	}
	
	/**
	 * Register service
	 * 
	 * @param string $id Unique service ID
	 * @param string $name Service name
	 * @param string|object|null $service Service object or invokable service class name
	 * @param array $options
	 * @param int $priority
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function registerService($id, $name, $service = null, $options = array(), $priority = 1)
	{
	    $name = $this->normalizeService($name);
	    
	    if (!preg_match($this->validIdPattern, $id)) {
	        throw new Exception\InvalidServiceException(sprintf(
	                "Service ID '%s' is not valid (ID must begin with lower/uppercase letter a-z, followed by one or more letters or digits)", $id));
	    }
	    
	    if (!preg_match($this->validNamePattern, $name)) {
	        throw new Exception\InvalidServiceException(sprintf(
                "Service name '%s' is not valid", $name));
	    }
	    
	    if(!isset($this->services[$name])){
	        $this->services[$name] = array();
	    }
	    
		$this->services[$name][$id] = array(
			'options' => $options,
			'priority' => $priority
		);
		
		$this->serviceNames[$id] = $name;
		
		// Mark service un-attached
		if(!isset($this->unAttached[$name])){
		    $this->unAttached[$name] = array();
		}
		
		$this->unAttached[$name][] = $id;
		
		// Register as invokable
		if(is_string($service)){
    		$this->getServicePluginManager()->setInvokableClass(
    			$id, 
    			$service
    		);
		} elseif (is_object($service)) {
		    $this->getServicePluginManager()->setService($id, $service);
		}
		
		return $this;
	}
	
	/**
	 * Loads specific service by ID
	 * 
	 * @param string $name Service name
	 * @param array $options Options to apply when first initialized
	 * @return ServiceInterface
	 */
	public function load($id, $options = null)
	{
	    $name = isset($this->serviceNames[$id])
	        ? $this->serviceNames[$id]
	        : null;
	    
	    if(!$name){
	        throw new Exception\ServiceNotFoundException(
                sprintf('Service ID "%s" does not exist', $id)
            );
	    }
	    
	    try{
	        /**
	         * Load pre-configured options
	         */
	        if($options == null){
	            $options = $this->services[$name][$id]['options'];
	        }
	        
	        $instance = $this->getServicePluginManager()->get($id, $options);
	        return $instance;
	    }
	    catch(\Zend\Loader\Exception\RuntimeException $e){
	        throw new Exception\InvalidServiceException(
	            sprintf('Service implementation "%s" is not a valid. Maybe the class doesn\'t implement ServiceInterface interface.', $id)
	        );
	    }
	}

	/**
	 * Test if a service exists
	 * 
	 * @param string $name
	 */
	public function exists($name)
	{
	    $name = $this->normalizeService($name);
	    
	    return  isset($this->services[$name]) && 
	            sizeof($this->services[$name]);
	}

	/**
	 * Attach listeners to event manager by service name
	 * 
	 * This method should not be called outside Broker.
	 * 
	 * @param CommandManager $commandManager
	 * @param string $name Name of the service
	 */
	public function attachListeners(CommandManager $commandManager, $name)
	{
	    $normalName = $this->normalizeService($name);
	    
	    if( !isset($this->services[$normalName]) || 
            !sizeof($this->services[$normalName]) ||
	        !sizeof($this->unAttached[$normalName])){
	        
	        return;
	    }
	    
	    // Attach all that have not been attached
	    foreach($this->unAttached[$normalName] as $id){
            $commandManager->attach(
                $name, 
                $this->load($id), 
                $this->services[$normalName][$id]['priority']
            );
	    }
	    
	    $this->unAttached[$normalName] = array();
	}
	
	/**
	 * Get cache adapter
	 *
	 * @return \Zend\Cache\Storage\StorageInterface
	 */
	public function getCache()
	{
	    return $this->cache;
	}
	
	/**
	 * Set cache adapter
	 * 
	 * @param StorageInterface $cache
	 * @return ServiceLoader
	 */
	public function setCache(StorageInterface $cache)
	{
	    $this->cache = $cache;
	    return $this;
	}
	
	/**
	 * Get definition based invoker
	 * 
	 * @return InvokerInterface
	 */
	protected function getInvoker()
	{
	    if($this->invoker == null){
	        $this->invoker = new DefinitionBased(
	            $this->getCache()        
            );
	    }
	    
	    return $this->invoker;
	}
	
	/**
	 * Retrieve service name in normal form
	 * 
	 * @param string $name
	 * @return string
	 */
    public final function normalizeService($name){
	    return strtolower($name);
	}
	
	/**
	 * Retrieve service manager
	 *
	 * @return \Valu\Service\ServiceManager
	 */
	protected function getServicePluginManager()
	{
	    if($this->servicePluginManager === null){
	        $this->servicePluginManager = new ServicePluginManager();
	        $this->servicePluginManager->setInvoker($this->getInvoker());
	         
	        $that = $this;
	
	        $this->servicePluginManager->addInitializer(function ($instance, $serviceManager) use ($that) {
	             
	            $name     = $serviceManager->getCreationInstanceName();
	            $options  = $serviceManager->getCreationInstanceOptions();
	             
	            /**
	             * Configure service
	            */
	            if($options !== null && sizeof($options) &&
	               $instance instanceof Feature\ConfigurableInterface){
	
	                $instance->setConfig($options);
	            }
	             
	            /**
	             * Provide shared invoker instance
	             */
	            if($instance instanceof Feature\InvokerAwareInterface &&
	               $instance instanceof Feature\DefinitionProviderInterface){
	
	                $instance->setInvoker($that->getInvoker());
	            }
	        });
	    }
	     
	    return $this->servicePluginManager;
	}
}