<?php
namespace ValuSo\Broker;

use ValuSo\Proxy\ServiceProxyGenerator;
use ValuSo\Exception;
use ValuSo\Annotation\AnnotationBuilder;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\Cache\Storage\StorageInterface;

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
     * Cache
     * 
     * @var StorageInterface
     */
    private $cache;
    
    /**
     * Proxy generator
     * 
     * @var ServiceProxyGenerator
     */
    private $proxyGenerator;
    
    /**
     * Annotation builder
     * 
     * @var \ValuSo\Annotation\AnnotationBuilder
     */
    private $annotationBuilder;
    
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
            $instance = $this->wrapService($name, $instance);
            
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
    
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function getCache()
    {
        return $this->cache;
    }
    
    public function setProxyGenerator(ServiceProxyGenerator $proxyGenerator)
    {
        $this->proxyGenerator = $proxyGenerator;
    }
    
    public function getProxyGenerator()
    {
        if (!$this->proxyGenerator) {
            $this->proxyGenerator = new ServiceProxyGenerator();
        }
        
        return $this->proxyGenerator;
    }

    /**
     * @return \ValuSo\Annotation\AnnotationBuilder
     */
    public function getAnnotationBuilder()
    {
        if (!$this->annotationBuilder) {
            $this->setAnnotationBuilder(new AnnotationBuilder());
        }
        
        return $this->annotationBuilder;
    }

	/**
     * @param \ValuSo\Annotation\AnnotationBuilder $annotationBuilder
     */
    public function setAnnotationBuilder($annotationBuilder)
    {
        $this->annotationBuilder = $annotationBuilder;
    }

	/**
     * Wrap service with proxy instance
     * 
     * @param string $name
     * @param object $instance
     * @return object
     */
    protected function wrapService($name, $instance)
    {
        if ($this->cache && ($fqcn = $this->cache->getItem($name)) && class_exists($fqcn)) {
            return new $fqcn($instance);
        } else {
            $className      = get_class($instance);
            $proxyGenerator = $this->getProxyGenerator();
            $fqcn           = $proxyGenerator->getProxyClassName($className);
            
            $config = $this->getAnnotationBuilder()->getServiceSpecification($instance);
            $config['name'] = $name; // Overwrite any previously configured name
            
            $proxyGenerator->generateProxyClass($instance, $config);
            require_once $proxyGenerator->getProxyFileName($className);
        
            $proxy = new $fqcn($instance);
            
            if ($this->cache) {
                $this->cache->setItem($name, $fqcn);
            }
        
            return $proxy;
        }
    }
}