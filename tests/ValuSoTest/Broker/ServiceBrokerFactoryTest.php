<?php
namespace ValuSoTest\Broker;

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
        $locator = new ServiceManager();
        $locator->setService('Config', [
            'valu_so' => [
                
            ]        
        ]);
        
        $this->assertInstanceOf('ValuSo\Broker\ServiceBroker', $this->serviceBrokerFactory->createService($locator));
    }
    
    public function testCreateServiceBrokerWithOneClosureService()
    {
        $locator = new ServiceManager();
        $locator->setService('Config', [
            'valu_so' => [
                'services' => [
                    'testid' => ['name' => 'Test.Service', 'service' => new ClosureService(function(){return true;})]
                ] 
            ]
        ]);
    
        $service = $this->serviceBrokerFactory->createService($locator);
        $this->assertTrue($service->execute('Test.Service', 'run')->first());
    }
    
    public function testCreateServiceBrokerWithOneClassService()
    {
        $locator = new ServiceManager();
        $locator->setService('Config', [
            'valu_so' => [
                'services' => [
                    'testid' => ['name' => 'Test.Service', 'service' => self::CLOSURE_SERVICE_CLASS]
                ]
            ]
        ]);
    
        $service = $this->serviceBrokerFactory->createService($locator);
    
        $this->assertEquals('executed', $service->execute('Test.Service', 'run')->first());
    }
    
    public function testCreateServiceBrokerWithOneFactoryService()
    {
        $locator = new ServiceManager();
        $locator->setService('Config', [
            'valu_so' => [
                'services' => [
                    'testid' => ['name' => 'Test.Service', 'factory' => self::CLOSURE_SERVICE_FACTORY]
                ]
            ]
        ]);
    
        $service = $this->serviceBrokerFactory->createService($locator);
    
        $this->assertEquals('executed', $service->execute('Test.Service', 'run')->first());
    }
}