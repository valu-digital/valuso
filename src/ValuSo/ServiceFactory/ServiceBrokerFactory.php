<?php
namespace Valu\Service\ServiceManager;

use ValuSo\Broker\ServiceBroker;
use ValuSo\Broker\ServiceLoader;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Cache\StorageFactory;

class ServiceBrokerFactory implements FactoryInterface
{

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config    = $serviceLocator->get('Configuration');
        $services  = $config['services'] ?: array();
        $cache     = isset($config['service_broker']['cache'])
            ? $config['service_broker']['cache'] : null;
        
        $scopedLocator = $serviceLocator->createScopedServiceManager();
        
        $loaderOptions = array(
            'services'	=> $services,
            'locator'	=> $scopedLocator,
        );
        
        $loader = new Loader(
            $loaderOptions
        );
        
        if(isset($cache['enabled']) 
           && $cache['enabled']){
            
            if (isset($cache['adapter'])) {
                unset($cache['enabled']);
                
                $cache = StorageFactory::factory($cache);
            } else {
                $cache = $serviceLocator->get('Cache');
            }
            
            $loader->setCache($cache);
        }
        
        // Look for services from global service locator as well
        $loader->getPluginManager()->addPeeringServiceManager($serviceLocator);
        
        $broker = new Broker(array('loader' => $loader));
        return $broker;
    }
}