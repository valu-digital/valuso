<?php
namespace ValuSo\Broker;

use	SplObjectStorage;
use	Valu\Service\Exception;
use	ValuSo\Feature;
use	Valu\Service\ServiceInterface;
use Valu\Service\Invoker\DefinitionBased;
use Zend\Cache\Storage\StorageInterface;
use	Zend\Loader\PluginClassLoader;
use Zend\EventManager\EventManagerInterface;
use	Zend\ServiceManager\ServiceLocatorInterface;
use	Zend\ServiceManager\ServiceLocatorAwareInterface;

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
	 * Plugin manager
	 * 
	 * @var \Valu\Service\ServiceManager
	 */
	private $serviceManager = null;

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
	
	public function __construct($options = null)
	{
		$this->services = array();
		$this->serviceNames = array();
		
		if(null !== $options){
			$this->setOptions($options);
		}
	}
	
	public function setOptions($options)
	{
		if (!is_array($options) && !$options instanceof \Traversable) {
			throw new \InvalidArgumentException(sprintf(
				'Expected an array or Traversable; received "%s"',
				(is_object($options) ? get_class($options) : gettype($options))
			));
		}
		
		foreach ($options as $key => $value){
			
			$key = strtolower($key);
			
			if($key == 'services'){
				$this->registerServices($value);
			}
			else if($key == 'locator' || $key == 'service_locator'){
			    $this->setServiceLocator($value);
			}
			else if($key == 'cache'){
			    $this->setCache($value);
			}
		}
		
		return $this;
	}
	
	public function setServiceLocator(ServiceLocatorInterface $locator)
	{
	    $this->getServiceManager()->setServiceLocator($locator);
	    
	    return $this;
	}
	
	public function getServiceLocator()
	{
	    return $this->getServiceManager()->getServiceLocator();
	}
	
	public function getServiceManager()
	{
	    if($this->serviceManager === null){
	        $this->serviceManager = new ServiceManager();
	        $this->serviceManager->setInvoker($this->getInvoker());
	        
	        $self = $this;
	         
	        $this->serviceManager->addInitializer(function ($instance, $serviceManager) use ($self) {
	            
	        	$name     = $serviceManager->getCreationInstanceName();
	        	$options  = $serviceManager->getCreationInstanceOptions();
	        	 
	        	/**
	        	 * Configure service
	        	*/
	        	if( $options !== null && sizeof($options) &&
	        		$instance instanceof Feature\ConfigurableInterface){
	        	    
	        		$instance->setConfig($options);
	        	}
	        
	        	/**
	        	 * Provide shared invoker instance
	        	 */
	        	if( $instance instanceof Feature\InvokerAwareInterface &&
	        			$instance instanceof Feature\DefinitionProviderInterface){
	        		 
	        		$instance->setInvoker($self->getInvoker());
	        	}
	        });
	    }
	    
	    return $this->serviceManager; 
	}
	
	/**
	 * Batch register services
	 * 
	 * @param array $services
	 * @throws \InvalidArgumentException
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function registerServices(array $services)
	{
		foreach($services as $key => $impl){
		    
		    $enabled = isset($impl['enabled']) ? $impl['enabled'] : true;
		    
		    if (!$enabled){
		        continue;
		    }
		    
			$id 		= isset($impl['id']) ? $impl['id'] : $key;
			$name 	    = isset($impl['name']) ? $impl['name'] : null;
			$class 		= isset($impl['class']) ? $impl['class'] : null;
			$factory 	= isset($impl['factory']) ? $impl['factory'] : null;
			$options 	= isset($impl['options']) ? $impl['options'] : null;
			$priority 	= isset($impl['priority']) ? $impl['priority'] : 1;

			if(is_null($options) && isset($impl['config'])){
			    $options = $impl['config'];
			}
			
			if(!$name){
				throw new \InvalidArgumentException('Service name is not defined for service: '.$id);
			}
			
			$this->registerService($id, $name, $class, $options, $priority);
			
			if($factory){
			    $this->setServiceFactory($id, $factory);
			}
		}
		
		return $this;
	}
	
	/**
	 * Register service
	 * 
	 * @param string $id Unique service ID
	 * @param string $name Service name
	 * @param string $class Invokable service class name
	 * @param array $options
	 * @param int $priority
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function registerService($id, $name, $class = null, $options = array(), $priority = 1)
	{

	    $name = $this->normalizeService($name);
	    
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
		if($class !== null){
    		$this->getServiceManager()->setInvokableClass(
    			$id, 
    			$class
    		);
		}
		
		return $this;
	}
	
	/**
	 * Define service factory class name for service ID
	 * 
	 * @param string $id
	 * @param string $factory
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function setServiceFactory($id, $factory)
	{
	    $this->getServiceManager()->setFactory($id, $factory);
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
	        
	        $instance = $this->getServiceManager()->get($id, $options);
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
	    
	    // Attach all
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
	 */
	public function setCache(StorageInterface $cache)
	{
	    $this->cache = $cache;
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
	
    public final function normalizeService($name){
	    return strtolower($name);
	}
}