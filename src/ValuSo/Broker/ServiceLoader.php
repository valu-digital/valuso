<?php
namespace ValuSo\Broker;

use	SplObjectStorage;
use Traversable;
use	ValuSo\Exception;
use	ValuSo\Feature;
use ValuSo\Command\CommandManager;
use Zend\Cache\StorageFactory;
use Zend\Cache\Storage\StorageInterface;
use	Zend\Loader\PluginClassLoader;
use Zend\EventManager\EventManagerInterface;
use	Zend\ServiceManager\ServiceLocatorInterface;
use	Zend\ServiceManager\ServiceLocatorAwareInterface;
use	Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ConfigInterface as ServiceManagerConfig;

class ServiceLoader{
	
	/**
	 * Registered service
	 * 
	 * @var PriorityQueue
	 */
	private $services;
	
	/**
	 * Service plugin manager
	 * 
	 * @var \ValuSo\Broker\ServicePluginManager
	 */
	private $servicePluginManager = null;
	
	/**
	 * Command manager
	 *
	 * @var CommandManager
	 */
	private $commandManager;
	
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
	 * Retrieve command manager
	 *
	 * @return \ValuSo\Command\CommandManager
	 */
	public function getCommandManager()
	{
	    if ($this->commandManager === null) {
	        $this->commandManager = new CommandManager();
	        $this->commandManager->setServiceLoader($this);
	    }
	     
	    return $this->commandManager;
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
			
			if (array_key_exists('enabled', $impl) && !$impl['enabled']) {
			    $this->disableService($id);
			}
			
			if($factory){
			    $this->getServicePluginManager()->setFactory(
		            $id, $factory);
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
	    $name = $this->normalizeServiceName($name);
	    $id = $this->normalizeServiceId($id);
	    
	    if (!preg_match($this->validIdPattern, $id)) {
	        throw new Exception\InvalidServiceException(sprintf(
	                "Service ID '%s' is not valid (ID must begin with lower/uppercase letter a-z, followed by one or more letters or digits)", $id));
	    }
	    
	    if (!preg_match($this->validNamePattern, $name)) {
	        throw new Exception\InvalidServiceException(sprintf(
                "Service name '%s' is not valid", $name));
	    }
	    
		$this->services[$id] = array(
	        'name' => $name,
			'options' => $options,
			'priority' => $priority
		);
		
		// Register as invokable
		if(is_string($service)){
    		$this->getServicePluginManager()->setInvokableClass(
    			$id, 
    			$service
    		);
		} elseif (is_object($service)) {
		    $this->getServicePluginManager()->setService($id, $service);
		}
		
		// Attach to command manager
		$this->services[$id]['listener'] = $this->getCommandManager()->attach($name, $id, $priority);
		
		return $this;
	}
	
	/**
	 * Enable service
	 * 
	 * @param string $id
	 * @return boolean
	 */
	public function enableService($id)
	{
	    if(!isset($this->services[$id])){
	        throw new Exception\ServiceNotFoundException(
                sprintf('Service ID "%s" does not exist', $id)
	        );
	    }
	    
	    if (!isset($this->services[$id]['listener'])) {
	        $this->services[$id]['listener'] = $this->getCommandManager()->attach(
                $this->services[$id]['name'], $id, $this->services[$id]['priority']);
	        
	        return true;
	    } else {
	        return false;
	    }
	}
	
	/**
	 * Disable service
	 *
	 * @param string $id
	 * @return boolean
	 */
	public function disableService($id)
	{
	    $id = $this->normalizeServiceId($id);
	    
	    if(!isset($this->services[$id])){
	        throw new Exception\ServiceNotFoundException(
                sprintf('Service ID "%s" does not exist', $id)
	        );
	    }
	    
	    if (isset($this->services[$id]['listener'])) {
	        $return = $this->getCommandManager()->detach($this->services[$id]['listener']);
	        $this->services[$id]['listener'] = null;
	        
	        return $return;
	    } else {
	        return false;
	    }
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
	    $id = $this->normalizeServiceId($id);
	    
	    if(!isset($this->services[$id])){
	        throw new Exception\ServiceNotFoundException(
                sprintf('Service ID "%s" does not exist', $id)
	        );
	    }
	    
        if($options == null){
            $options = $this->services[$id]['options'];
        }
        
        return $this->getServicePluginManager()->get($id, $options, true, true);
	}
	
	/**
	 * Retrieve IDs for services
	 * 
	 * @return array
	 */
	public function getServiceIds()
	{
	    return array_keys($this->services);
	}
	
	/**
	 * Retrieve options for service
	 * 
	 * @param string $id
	 * @return array|null
	 * @throws Exception\ServiceNotFoundException
	 */
	public function getServiceOptions($id)
	{
	    $id = $this->normalizeServiceId($id);
	    
	    if(!isset($this->services[$id])){
	        throw new Exception\ServiceNotFoundException(
                sprintf('Service ID "%s" does not exist', $id)
	        );
	    }
	    
	    return $this->services[$id]['options'];
	}
	
	/**
	 * Retrieve name for service
	 *
	 * @param string $id
	 * @return array|null
	 * @throws Exception\ServiceNotFoundException
	 */
	public function getServiceName($id)
	{
	    $id = $this->normalizeServiceId($id);
	     
	    if(!isset($this->services[$id])){
	        throw new Exception\ServiceNotFoundException(
	                sprintf('Service ID "%s" does not exist', $id)
	        );
	    }
	     
	    return $this->services[$id]['name'];
	}

	/**
	 * Test if a service exists
	 * 
	 * Any services that are currently disabled, are not
	 * taken into account.
	 * 
	 * @param string $name
	 */
	public function exists($name)
	{
	    $name = $this->normalizeServiceName($name);
	    return $this->getCommandManager()->hasListeners($name);
	}
	
	/**
	 * Retrieve service manager
	 *
	 * @return \ValuSo\Broker\ServicePluginManager
	 */
	public function getServicePluginManager()
	{
	    if($this->servicePluginManager === null){
	        $this->servicePluginManager = new ServicePluginManager();
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
	        });
	    }
	     
	    return $this->servicePluginManager;
	}
	
	/**
	 * Retrieve service name in normal form
	 * 
	 * @param string $name
	 * @return string
	 */
    public final function normalizeServiceName($name){
	    return strtolower($name);
	}
	
	/**
	 * Retrieve service ID in normal form
	 *
	 * @param string $id
	 * @return string
	 */
	public final function normalizeServiceId($id){
	    return strtolower($id);
	}
}