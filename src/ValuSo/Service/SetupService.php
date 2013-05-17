<?php
namespace ValuSo\Service;

use ValuSo\Broker\ServiceBrokerFactory;
use ValuSo\Broker\ServicePluginManager;
use ValuSetup\Service\AbstractSetupService;
use Zend\Cache\Storage\FlushableInterface;
use Zend\Cache\Storage\ClearByPrefixInterface;

class SetupService extends AbstractSetupService
{
    public function setup(array $options = array())
    {
        $this->clearCache();
        $this->buildProxyClasses();
        return true;
    }
    
    public function clearCache()
    {
        $broker = $this->createServiceBroker();
        $cache  = $broker->getLoader()->getServicePluginManager()->getCache();
        
        if ($cache instanceof ClearByPrefixInterface) {
            $cache->clearByPrefix(ServicePluginManager::CACHE_ID_PREFIX);
        } elseif ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
    
    public function buildProxyClasses()
    {
        $broker = $this->createServiceBroker();
        $pluginManager = $broker->getLoader()->getServicePluginManager();
        $serviceIds = $broker->getLoader()->getServiceIds();
        
        $pluginManager->setProxyAutoCreateStrategy(ServicePluginManager::PROXY_AUTO_CREATE_ALWAYS);
        
        if (sizeof($serviceIds)) {
            foreach ($serviceIds as $serviceId) {
                $instance = $pluginManager->get($serviceId, array(), true, false);
                $pluginManager->wrapService($serviceId, $instance);
            }
        }
    }
    
    /**
     * @return \ValuSo\Broker\ServiceBroker
     */
    private function createServiceBroker()
    {
        $factory = new ServiceBrokerFactory();
        $locator = $this->getServiceLocator();
        
        return $factory->createService($locator);
    }
}