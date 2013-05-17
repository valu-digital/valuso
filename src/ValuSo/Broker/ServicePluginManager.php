<?php
namespace ValuSo\Broker;

use ValuSo\Exception\AnnotationException;
use ReflectionClass;
use ValuSo\Proxy\ServiceProxyGenerator;
use ValuSo\Exception;
use ValuSo\Annotation\AnnotationBuilder;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\Cache\Storage\StorageInterface;
use Zend\ServiceManager\InitializerInterface;
use Zend\Cache\StorageFactory;

/**
 * Service manager is responsible of maintaining list of registered
 * services and initializing the services as needed
 * 
 */
class ServicePluginManager extends AbstractPluginManager
{
    const PROXY_AUTO_CREATE_MTIME = 'mtime';
    
    const PROXY_AUTO_CREATE_ALWAYS = 'always';
    
    const CACHE_ID_PREFIX = 'valu_so_proxy_';
    
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
     * Stack of "fetch as service" flags
     *
     * @var array
     */
    private $instanceAsService = array();

    /**
     * Cache
     * 
     * @var StorageInterface
     */
    protected $cache;
    
    /**
     * Proxy generator
     * 
     * @var ServiceProxyGenerator
     */
    protected $proxyGenerator;
    
    /**
     * Annotation builder
     * 
     * @var \ValuSo\Annotation\AnnotationBuilder
     */
    protected $annotationBuilder;
    
    /**
     * Directory for proxy files
     * 
     * @var string
     */
    protected $proxyDir = null;
    
    /**
     * Proxy class namespace
     * 
     * @var string
     */
    protected $proxyNs = null;
    
    /**
     * Auto-create strategy for proxy classes
     *
     * @var string
     */
    protected $proxyAutoCreateStrategy;
    
    /**
     * Wrapped service instances
     * 
     * @var array
     */
    protected $wrapped = array();
    
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
        $this->instanceAsService[] = $fetchAsService;
        
    	$instance = parent::get($name, $options, $usePeeringServiceManagers);
    	
        if ($fetchAsService && !is_callable($instance)) {
            $cName = $this->canonicalizeName($name);
            
            if (isset($this->wrapped[$cName])) {
                return $this->wrapped[$cName];
            }
            
            $instance = $this->wrapService($cName, $instance);
            $this->wrapped[$cName] = $instance;
        }
        
        array_pop($this->instanceNames);
        array_pop($this->instanceOptions);
        array_pop($this->instanceAsService);
        
    	return $instance;
    }
    
    /**
     * @see \Zend\ServiceManager\AbstractPluginManager::setService()
     */
    public function setService($name, $service, $shared = true)
    {
        unset($this->wrapped[$this->canonicalizeName($name)]);
        return parent::setService($name, $service, $shared);
    }
    
    /**
     * @see \Zend\ServiceManager\ServiceManager::setInvokableClass()
     */
    public function setInvokableClass($name, $invokableClass, $shared = null)
    {
        unset($this->wrapped[$this->canonicalizeName($name)]);
        return parent::setInvokableClass($name, $invokableClass, $shared);
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
     * Retrieve "fetch as service" flag for instance that is
     * currently being initialized
     *
     * You may use this method in initializers.
     *
     * @return string|NULL
     */
    public function getCreationInstanceFetchAsService()
    {
        return  sizeof($this->instanceAsService)
                ? $this->instanceAsService[sizeof($this->instanceAsService)-1]
                : null;
    }
    
    /**
     * @see \Zend\ServiceManager\AbstractPluginManager::validatePlugin()
     */
    public function validatePlugin($plugin)
    {
        // Validate only services
        if (!$this->getCreationInstanceFetchAsService()) {
            return true;
        }
        
        if(is_object($plugin)){
        	return true;
        }

        throw new Exception\InvalidServiceException(sprintf(
            'Service of type %s is invalid; object expected',
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
                StorageFactory::factory(['adapter' => 'memory']));
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
            $this->proxyGenerator = new ServiceProxyGenerator($this->getProxyDir(), $this->getProxyNs());
        }
        
        return $this->proxyGenerator;
    }

    /**
     * @return \ValuSo\Annotation\AnnotationBuilder
     */
    public function getAnnotationBuilder()
    {
        if (!$this->annotationBuilder) {
            $this->setAnnotationBuilder(
                $this->getServiceLocator()->get('valu_so.annotation_builder'));
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
	 * Retrieve proxy namespace
	 * 
     * @return string
     */
    public function getProxyDir()
    {
        return $this->proxyDir;
    }

	/**
	 * Retrieve proxy namespace
	 * 
     * @return string
     */
    public function getProxyNs()
    {
        return $this->proxyNs;
    }

	/**
	 * Retrieve current proxy auto creation strategy (if any)
	 * 
     * @return string
     */
    public function getProxyAutoCreateStrategy()
    {
        return $this->proxyAutoCreateStrategy;
    }

	/**
	 * Set current proxy auto creation strategy
	 * 
     * @param string $proxyAutoCreateStrategy
     */
    public function setProxyAutoCreateStrategy($proxyAutoCreateStrategy)
    {
        $this->proxyAutoCreateStrategy = $proxyAutoCreateStrategy;
    }

	/**
	 * Set proxy directory
	 * 
     * @param string $proxyDir
     */
    public function setProxyDir($proxyDir)
    {
        $this->proxyDir = $proxyDir;
    }

	/**
	 * Set proxy namespace
	 * 
     * @param string $proxyNs
     */
    public function setProxyNs($proxyNs)
    {
        $this->proxyNs = $proxyNs;
    }

	/**
     * Wrap service with proxy instance
     * 
     * @param string $serviceId
     * @param object $instance
     * @return object
     */
    public function wrapService($serviceId, $instance)
    {
        $serviceId = $this->canonicalizeName($serviceId);
        
        if (($fqcn = $this->getCache()->getItem(self::CACHE_ID_PREFIX . $serviceId)) 
             && class_exists($fqcn) 
             && $this->getProxyAutoCreateStrategy() !== self::PROXY_AUTO_CREATE_ALWAYS) {
            
            return new $fqcn($instance);
        } else {
            $className      = get_class($instance);
            $proxyGenerator = $this->getProxyGenerator();
            $fqcn           = $proxyGenerator->getProxyClassName($className);
            $file           = $proxyGenerator->getProxyFilename($className);
            
            // Re-generate proxy class if original service class file has changed
            if ($this->getProxyAutoCreateStrategy() === self::PROXY_AUTO_CREATE_MTIME && file_exists($file)) {
                $reflection = new ReflectionClass($className);
                $instanceFile = $reflection->getFileName();
                
                if (filemtime($instanceFile) > filemtime($file)) {
                    unlink($file);
                }
            }

            if (!file_exists($file) || $this->getProxyAutoCreateStrategy() === self::PROXY_AUTO_CREATE_ALWAYS) {
                try {
                    $config = $this->getAnnotationBuilder()->getServiceSpecification($instance);
                } catch(\Exception $e) {
                    throw new AnnotationException(
                        sprintf('Unable to parse annotations for service %s (%s). Reason: %s', $serviceId, $file, $e->getMessage()));
                }
                
                $config['service_id'] = $serviceId; // Overwrite any previously configured service ID
                
                $proxyGenerator->generateProxyClass($instance, $config);
            }
            
            require_once $file;
            $proxy = new $fqcn($instance);
            
            $this->getCache()->setItem(self::CACHE_ID_PREFIX . $serviceId, $fqcn);
            
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