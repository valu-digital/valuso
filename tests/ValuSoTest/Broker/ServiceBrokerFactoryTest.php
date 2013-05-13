<?php
namespace ValuSoTest\Broker;

use Zend\EventManager\EventManager;

use ValuSoTest\TestAsset\ClosureService;

use Zend\ServiceManager\ServiceManager;
use ValuSo\Broker\ServiceBrokerFactory;
use PHPUnit_Framework_TestCase;

/**
 * ServiceBroker test case.
 */
class ServiceBrokerFactoryTest extends PHPUnit_Framework_TestCase
{
    
    const CLOSURE_SERVICE_CLASS = 'ValuSoTest\TestAsset\ClosureService';
    
    const CLOSURE_SERVICE_FACTORY = 'ValuSoTest\TestAsset\ClosureServiceFactory';
    
    /**
     *
     * @var ServiceBroker
     */
    private $serviceBrokerFactory;
    
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        ClosureService::setDefaultClosure(function(){return 'executed';});
        $this->serviceBrokerFactory = new ServiceBrokerFactory();
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        ClosureService::setDefaultClosure(null);
        $this->serviceBroker = null;
    
        parent::tearDown();
    }
    
    public function testCreateEmptyServiceBroker()
    {
        $sm = $this->configureServiceManager();
        $this->assertInstanceOf('ValuSo\Broker\ServiceBroker', $this->serviceBrokerFactory->createService($sm));
    }
    
    public function testServiceBrokerFactoryTriggersInitEvent()
    {
        $triggered = false;
        $sm = $this->configureServiceManager();
        $evm = $sm->get('EventManager');
        $evm->attach('valu_so.servicebroker.init', function() use(&$triggered) {$triggered = true;});
        
        $this->assertInstanceOf('ValuSo\Broker\ServiceBroker', $this->serviceBrokerFactory->createService($sm));
        $this->assertTrue($triggered);
    }
    
    public function testCreateServiceBrokerWithOneClosureService()
    {
        $sm = $this->configureServiceManager(['testid' => ['name' => 'Test.Service', 'service' => new ClosureService(function(){return true;})]]);
    
        $service = $this->serviceBrokerFactory->createService($sm);
        $this->assertTrue($service->execute('Test.Service', 'run')->first());
    }
    
    public function testCreateServiceBrokerWithOneClassService()
    {
        $sm = $this->configureServiceManager(['testid' => ['name' => 'Test.Service', 'service' => self::CLOSURE_SERVICE_CLASS]]);
        $service = $this->serviceBrokerFactory->createService($sm);
    
        $this->assertEquals('executed', $service->execute('Test.Service', 'run')->first());
    }
    
    public function testCreateServiceBrokerWithOneFactoryService()
    {
        $sm = $this->configureServiceManager(['testid' => ['name' => 'Test.Service', 'factory' => self::CLOSURE_SERVICE_FACTORY]]);
        $service = $this->serviceBrokerFactory->createService($sm);
    
        $this->assertEquals('executed', $service->execute('Test.Service', 'run')->first());
    }
    
    private function configureServiceManager($services = array())
    {
        $sm = new ServiceManager();
        $sm->setService('Config', [
            'valu_so' => [
                'services' => $services
            ]
        ]);
        $sm->setService('EventManager', new EventManager());
        
        return $sm;
    }
}