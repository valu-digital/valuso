<?php
namespace ValuSoTest\Broker;

use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Queue\Job\ServiceJobFactory;
use ValuSo\Broker\ServiceBroker;
use Zend\ServiceManager\ServiceManager;
use SlmQueue\Job\JobPluginManager;

class ServiceJobFactoryTest extends TestCase
{
    protected $serviceJobFactory;
    
    protected $sm;
    
    protected $jobPluginManager;
    
    protected $broker;
    
    protected function setUp()
    {
        $this->broker = new ServiceBroker();
        
        $this->sm = new ServiceManager();
        $this->sm->setService('ServiceBroker', $this->broker);
        
        $this->jobPluginManager = new JobPluginManager();
        $this->jobPluginManager->setServiceLocator($this->sm);
        
        $this->serviceJobFactory = new ServiceJobFactory();
    }
    
    public function testCreateService()
    {
        $service = $this->serviceJobFactory->createService($this->jobPluginManager);
        $this->assertInstanceOf('ValuSo\Queue\Job\ServiceJob', $service);
        $this->assertSame($this->broker, $service->getServiceBroker());
    }
}