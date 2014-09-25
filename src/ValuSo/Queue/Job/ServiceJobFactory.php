<?php
namespace ValuSo\Queue\Job;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * ServiceBroker factory
 *
 */
class ServiceJobFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $job = new ServiceJob();
        $job->setServiceBroker($serviceLocator->getServiceLocator()->get('ServiceBroker'));
        
        return $job;
    }
}