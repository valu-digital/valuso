<?php
namespace Valu\Service\ServiceManager;

use Zend\Cache\Storage\StorageInterface;
use Zend\Mvc\Service\ServiceManagerConfig;
use ValuSo\Broker\ServiceBroker;
use ValuSo\Broker\ServiceLoader;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Cache\StorageFactory;

class ServiceBrokerFactory implements FactoryInterface
{

    /**
     * Create a ServiceBroker
     * 
     * {@see ValuSo\Broker\ServiceBroker} uses {@see Zend\ServiceManager\ServiceManager} internally to initialize service
     * instances. {@see Zend\Mvc\Service\ServiceManagerConfig} for how to configure service manager.
     * 
     * This factory uses following configuration scheme:
     * <code>
     * [
     *     'valu_so' => [
     *         'use_main_locator'   => <true>|<false>, // Set true to add main service locator as a peering service manager
     *         'factories'          => [...] // See Zend\Mvc\Service\ServiceManagerConfig
     *         'invokables'         => [...] // See Zend\Mvc\Service\ServiceManagerConfig
     *         'abstract_factories' => [...] // See Zend\Mvc\Service\ServiceManagerConfig
     *         'shared'             => [...] // See Zend\Mvc\Service\ServiceManagerConfig
     *         'aliases'            => [...] // See Zend\Mvc\Service\ServiceManagerConfig
     *         'services' => [
     *             '<id>' => [
     *                 'name'     => '<ServiceName>', // Name of the service
     *                 'options'  => [...] // [optional] Options passed to service when initialized,
     *                 'class'    => '<Class>', // [optional] Service class (same as defining it in 'invokables')
     *                 'factory'  => '<Class>', // [optional] Factory class  (same as defining it in 'factories')
     *                 'service'  => <Object|Closure> // [optional] Service object/closure
     *             ]
     *         ]
     *     ]
     * ]
     * </code>
     * 
     * @see \Zend\ServiceManager\FactoryInterface::createService()
     * @return ServiceBroker
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config    = $serviceLocator->get('Config');
        $config    = empty($config['valu_so']) ? [] : $config['valu_so'];
        
        $cacheConfig = isset($config['cache']) ? $config['cache'] : null;
        
        /**
         * Configure cache
         */
        $cache = $this->configureCache($serviceLocator, $cacheConfig);
        unset($config['cache']);
        
        /**
         * Configure loader
         */
        $loaderOptions = array(
            'locator' => $serviceLocator->createScopedServiceManager()
        );
        
        if ($cache) {
            $loaderOptions['cache'] = $cache;
        }
        
        if (!empty($config['services'])) {
            $loaderOptions['services'] = $config['services'];
        }
        unset($config['services']);
        
        if (isset($config['use_main_locator']) && $config['use_main_locator']) {
            $peeringManager = $serviceLocator;
        } else {
            $peeringManager = null;
        }
        unset($config['use_main_locator']);
        
        // Pass other configurations as a service manager configuration
        if (!empty($config)) {
            $smConfig = new ServiceManagerConfig($config);
            $loaderOptions['service_manager'] = $smConfig;
        }
        
        // Initialize loader
        $loader = new ServiceLoader(
            $loaderOptions
        );
        
        if ($peeringManager) {
            $loader->addPeeringServiceManager($peeringManager);
        }
        
        $broker = new ServiceBroker();
        $broker->setLoader($loader);
        
        return $broker;
    }
    
    /**
     * Configure cache
     * 
     * @param ServiceLocatorInterface $serviceLocator
     * @param unknown_type $cacheConfig
     * @return StorageInterface|null
     */
    private function configureCache(ServiceLocatorInterface $serviceLocator, $cacheConfig)
    {
        if($cacheConfig && isset($cacheConfig['enabled']) && $cacheConfig['enabled']){
        
            if (isset($cacheConfig['adapter'])) {
                unset($cacheConfig['enabled']);
        
                $cache = StorageFactory::factory($cacheConfig);
            } else {
                $cache = $serviceLocator->get('Cache');
        
                if (!$cache instanceof StorageInterface) {
                    $cache = null;
                }
            }
        } else {
            $cache = null;
        }
        
        return $cache;
    }
}