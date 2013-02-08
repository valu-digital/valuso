<?php
namespace ValuSo\Broker;

use ValuSo\Feature\InvokerAwareInterface;
use ValuSo\Invoker\InvokerInterface;
use ValuSo\Exception;
use Zend\ServiceManager\AbstractPluginManager;

/**
 * Service manager is responsible of maintaining list of registered
 * services and initializing the services as needed
 * 
 */
class ServicePluginManager extends AbstractPluginManager
{
    
    /**
     * Stack of instance names
     * 
     * @var array
     */
    private $instanceNames = array();
    
    /**
     * Stack of instance options
     * 
     * @var array
     */
    private $instanceOptions = array();
    
    /**
     * Invoker instance
     * 
     * @var \ValuSo\Invoker\InvokerInterface
     */
    private $invoker;
    
    /**
     * Retrieve a service from the manager by name
     *
     * Allows passing an array of options to use when creating the instance.
     * createFromInvokable() will use these and pass them to the instance
     * constructor if not null and a non-empty array.
     *
     * @param  string $name
     * @param  array $options
     * @param  bool $usePeeringServiceManagers
     * @return object
     */
    public function get($name, $options = array(), $usePeeringServiceManagers = true)
    {
        $this->instanceNames[]     = $name;
        $this->instanceOptions[]   = $options;
        
    	$instance = parent::get($name, $options, $usePeeringServiceManagers);
    	
    	array_pop($this->instanceNames);
        array_pop($this->instanceOptions);
        
        $cName = $this->canonicalizeName($name);
        
        // Wrap instance if it is not callable
        if (!is_callable($instance)) {
            $instance = $this->wrapService($instance);
            
            // Replace cached instance with wrapper
            if (isset($this->instances[$cName])) {
                $this->instances[$cName] = $instance;
            }
        }
        
    	return $instance;
    }
    
    /**
     * Retrieve name of the instance that is currently being
     * created or initialized
     * 
     * You may use this method in initializers.
     * 
     * @return string|NULL
     */
    public function getCreationInstanceName()
    {
        return  sizeof($this->instanceNames)
                ? $this->instanceNames[sizeof($this->instanceNames)-1]
                : null;
    }
    
    /**
     * Retrieve options for the instance that is currently being
     * created or initialized
     *
     * You may use this method in initializers.
     *
     * @return string|NULL
     */
    public function getCreationInstanceOptions()
    {
        return  sizeof($this->instanceOptions)
        ? $this->instanceOptions[sizeof($this->instanceOptions)-1]
        : null;
    }
    
    /**
     * @see \Zend\ServiceManager\AbstractPluginManager::validatePlugin()
     */
    public function validatePlugin($plugin)
    {
        if(is_object($plugin)){
        	return true;
        }

        throw new Exception\InvalidServiceException(sprintf(
            'Controller of type %s is invalid; object expected',
            gettype($plugin)
        ));
    }
    
    /**
     * Sets invoker to use with invoker aware service wrappers
     * 
     * @param InvokerInterface $invoker
     */
    public function setInvoker(InvokerInterface $invoker)
    {
        $this->invoker = $invoker;
    }
    
    /**
     * Retrieve invoker instance
     * 
     * @return \ValuSo\Invoker\InvokerInterface
     */
    public function getInvoker()
    {
        return $this->invoker;
    }
    
    /**
     * Wraps service with special service wrapper 
     * 
     * @param object $instance
     * @return \ValuSo\Broker\ServiceWrapper
     */
    protected function wrapService($instance)
    {
        $wrapper = new ServiceWrapper($instance);
        
        if ($wrapper instanceof InvokerAwareInterface) {
            
            if (!$this->getInvoker()) {
                throw new Exception\RuntimeException(
                    'ServiceManager is not configured properly; invoker is not set but it is requested by wrapper');
            }
            
            $wrapper->setInvoker($this->getInvoker());
        }
        
        return $wrapper;
    }
}