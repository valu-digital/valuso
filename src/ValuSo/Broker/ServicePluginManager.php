<?php
namespace ValuSo\Broker;

use Zend\ServiceManager\InitializerInterface;

use Zend\Cache\StorageFactory;

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
     * @param  bool $fetchAsService 
     * @return object
     */
    public function get($name, $options = array(), $usePeeringServiceManagers = true, $fetchAsService = false)
    {
        $this->instanceNames[]     = $name;
        $this->instanceOptions[]   = $options;
        
    	$instance = parent::get($name, $options, $usePeeringServiceManagers);
    	
    	array_pop($this->instanceNames);
        array_pop($this->instanceOptions);
        
        $cName = $this->canonicalizeName($name);
        
        // Wrap instance if it is not callable
        if ($fetchAsService && !is_callable($instance)) {
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
    
    /**
     * Set cache storage
     * 
     * @param StorageInterface $cache
     */
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * Get cache storage
     * 
     * @return \Zend\Cache\Storage\StorageInterface
     */
    public function getCache()
    {
        if (!$this->cache) {
            $this->setCache(
                StorageFactory::factory(['adapter' => 'array']));
        }
        
        return $this->cache;
    }
    
    /**
     * Set proxy generator
     * 
     * @param ServiceProxyGenerator $proxyGenerator
     */
    public function setProxyGenerator(ServiceProxyGenerator $proxyGenerator)
    {
        $this->proxyGenerator = $proxyGenerator;
    }
    
    /**
     * Retrieve proxy generator
     * 
     * @return \ValuSo\Proxy\ServiceProxyGenerator
     */
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
     * @param string $serviceId
     * @param object $instance
     * @return object
     */
    protected function wrapService($serviceId, $instance)
    {
        if (($fqcn = $this->getCache()->getItem($serviceId)) && class_exists($fqcn)) {
            return new $fqcn($instance);
        } else {
            $className      = get_class($instance);
            $proxyGenerator = $this->getProxyGenerator();
            $fqcn           = $proxyGenerator->getProxyClassName($className);
            
            $config = $this->getAnnotationBuilder()->getServiceSpecification($instance);
            $config['service_id'] = $serviceId; // Overwrite any previously configured name

            $proxyGenerator->generateProxyClass($instance, $config);
            $proxy = $proxyGenerator->createProxyClassInstance($instance);
            
            if ($this->getCache()) {
                $this->getCache()->setItem($serviceId, $fqcn);
            }
            
            // Run initializers for the proxy class
            foreach ($this->initializers as $initializer) {
                if ($initializer instanceof InitializerInterface) {
                    $initializer->initialize($proxy, $this);
                } elseif (is_object($initializer) && is_callable($initializer)) {
                    $initializer($proxy, $this);
                } else {
                    call_user_func($initializer, $proxy, $this);
                }
            }
            
            return $proxy;
        }
    }
}