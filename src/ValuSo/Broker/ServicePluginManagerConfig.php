<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Mvc
 */

namespace ValuSo\Broker;

use Zend\Cache\Storage\StorageInterface;
use Zend\Cache\StorageFactory;
use Zend\ServiceManager\ConfigInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * @category   Zend
 * @package    Zend_Mvc
 * @subpackage Service
 */
class ServicePluginManagerConfig implements ConfigInterface
{
    /**
     * Initializers that should be registered
     *
     * @var array
     */
    protected $initializers = array();
    
    /**
     * Services that can be instantiated without factories
     *
     * @var array
     */
    protected $invokables = array();

    /**
     * Service factories
     *
     * @var array
     */
    protected $factories = array();

    /**
     * Abstract factories
     *
     * @var array
     */
    protected $abstractFactories = array();

    /**
     * Aliases
     *
     * @var array
     */
    protected $aliases = array();

    /**
     * Shared services
     *
     * Services are shared by default; this is primarily to indicate services
     * that should NOT be shared
     *
     * @var array
     */
    protected $shared = array();
    
    /**
     * Cache storage instance or configurations
     * 
     * @var array|StorageInterface
     */
    protected $cache;
    
    /**
     * Proxy cache directory
     * @var string
     */
    protected $proxyDir;
    
    /**
     * Proxy class namespace
     * @var string
     */
    protected $proxyNs;
    
    /**
     * Auto-create strategy for proxy classes
     * 
     * @var string
     */
    protected $proxyAutoCreateStrategy;

    /**
     * Constructor
     *
     * Merges internal arrays with those passed via configuration
     *
     * @param  array $configuration
     */
    public function __construct(array $configuration = array())
    {
        if (isset($configuration['initializers'])) {
            $this->initializers = array_merge($this->initializers, $configuration['initializers']);
        }
        
        if (isset($configuration['invokables'])) {
            $this->invokables = array_merge($this->invokables, $configuration['invokables']);
        }

        if (isset($configuration['factories'])) {
            $this->factories = array_merge($this->factories, $configuration['factories']);
        }

        if (isset($configuration['abstract_factories'])) {
            $this->abstractFactories = array_merge($this->abstractFactories, $configuration['abstract_factories']);
        }

        if (isset($configuration['aliases'])) {
            $this->aliases = array_merge($this->aliases, $configuration['aliases']);
        }

        if (isset($configuration['shared'])) {
            $this->shared = array_merge($this->shared, $configuration['shared']);
        }
        
        if (isset($configuration['cache'])) {
            $this->cache = $configuration['cache'];
        }
        
        if (isset($configuration['proxy_dir'])) {
            $this->proxyDir = $configuration['proxy_dir'];
        }

        if (isset($configuration['proxy_ns'])) {
            $this->proxyNs = $configuration['proxy_ns'];
        }
        
        if (isset($configuration['proxy_auto_create_strategy'])) {
            $this->proxyAutoCreateStrategy = $configuration['proxy_auto_create_strategy'];
        }
    }

    /**
     * Configure the provided service manager instance with the configuration
     * in this class.
     *
     * In addition to using each of the internal properties to configure the
     * service manager, also adds an initializer to inject ServiceManagerAware
     * and ServiceLocatorAware classes with the service manager.
     *
     * @param  ServiceManager $serviceManager
     * @return void
     */
    public function configureServiceManager(ServiceManager $serviceManager)
    {
        foreach ($this->initializers as $name => $initializer) {
            $serviceManager->addInitializer($initializer);
        }
        
        foreach ($this->invokables as $name => $class) {
            $serviceManager->setInvokableClass($name, $class);
        }

        foreach ($this->factories as $name => $factoryClass) {
            $serviceManager->setFactory($name, $factoryClass);
        }

        foreach ($this->abstractFactories as $factoryClass) {
            $serviceManager->addAbstractFactory($factoryClass);
        }

        foreach ($this->aliases as $name => $service) {
            $serviceManager->setAlias($name, $service);
        }

        foreach ($this->shared as $name => $value) {
            $serviceManager->setShared($name, $value);
        }
        
        if ($this->cache && (is_array($this->cache) || $this->cache instanceof \Traversable)) {
            $cache = StorageFactory::factory($this->cache);
            $serviceManager->setCache($cache);
        } elseif ($this->cache && $this->cache instanceof StorageInterface) {
            $serviceManager->setCache($this->cache);
        }
        
        if ($this->proxyDir) {
            $serviceManager->setProxyDir($this->proxyDir);
        }
        
        if ($this->proxyNs) {
            $serviceManager->setProxyNs($this->proxyNs);
        }
        
        if ($this->proxyAutoCreateStrategy) {
            $serviceManager->setProxyAutoCreateStrategy($this->proxyAutoCreateStrategy);
        }
    }
}
