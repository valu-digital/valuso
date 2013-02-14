<?php
namespace ValuSoTest\Broker;

use ValuSo\Command\Command;

use ValuSo\Command\CommandManager;
use ValuSoTest\TestAsset\ClosureService;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use Zend\Cache\StorageFactory;
use Zend\EventManager\ResponseCollection;
use PHPUnit_Framework_TestCase;
use ValuSo\Broker\ServiceLoader;

/**
 * ServiceLoader test case.
 */
class ServiceLoaderTest extends PHPUnit_Framework_TestCase
{

    const CLOSURE_SERVICE_CLASS = 'ValuSoTest\TestAsset\ClosureService';
    
    const CLOSURE_SERVICE_FACTORY = 'ValuSoTest\TestAsset\ClosureServiceFactory';
    
    /**
     * @var ServiceLoader
     */
    private $serviceLoader;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        // TODO Auto-generated ServiceLoaderTest::setUp()
        
        $this->serviceLoader = new ServiceLoader();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        // TODO Auto-generated ServiceLoaderTest::tearDown()
        $this->serviceLoader = null;
        
        parent::tearDown();
    }

    public function testConfigureServiceManager()
    {
        $smConfig = new ServiceManagerConfig(['invokables' => ['testid' => self::CLOSURE_SERVICE_CLASS]]);
        
        $this->serviceLoader->configureServiceManager($smConfig);
        $this->serviceLoader->registerService('testid', 'testservice');
        
        $this->assertInstanceof(self::CLOSURE_SERVICE_CLASS, $this->serviceLoader->load('testid'));
    }

    public function testAddPeeringServiceManager()
    {
        $c = new ClosureService();
        $peer = new ServiceManager();
        $peer->setService('testid', $c);
        
        $this->serviceLoader->addPeeringServiceManager($peer);
        $this->serviceLoader->registerService('testid', 'Test.Service');
        
        $this->assertSame($c, $this->serviceLoader->load('testid'));
    }
    
    public function testAddInitializer()
    {
        $this->serviceLoader->addInitializer(function($instance){$instance->closure = function(){return 'changed';};});
        $this->serviceLoader->registerService('testid', 'Test.Service', self::CLOSURE_SERVICE_CLASS);
        
        $this->assertEquals(
            'changed',
            $this->serviceLoader->load('testid')->__invoke(''));
    }

    public function testSetGetServiceLocator()
    {
        $locator  = new ServiceManager();
        $this->assertEquals(
            $this->serviceLoader,
            $this->serviceLoader->setServiceLocator($locator)
        );
        
        $this->assertEquals($locator, $this->serviceLoader->getServiceLocator());
    }
    
    public function testGetCommandManager()
    {
        $this->assertInstanceof('ValuSo\Command\CommandManager', $this->serviceLoader->getCommandManager());
    }
    
    public function testRegisterServiceUsingClassName()
    {
        $this->serviceLoader->registerService('testid', 'test.service', self::CLOSURE_SERVICE_CLASS);
        $this->assertInstanceof(self::CLOSURE_SERVICE_CLASS, $this->serviceLoader->load('testid'));
    }
    
    public function testRegisterServiceUsingInstance()
    {
        $this->serviceLoader->registerService('testid', 'testservice', new ClosureService());
        $this->assertInstanceof(self::CLOSURE_SERVICE_CLASS, $this->serviceLoader->load('testid'));
    }
    
    public function testRegisterServiceWithOptions()
    {
        $this->serviceLoader->registerService('testid', 'testservice', self::CLOSURE_SERVICE_CLASS, ['opt' => 'value']);
        $this->assertEquals(['opt' => 'value'], $this->serviceLoader->load('testid')->config);
    }
    
    /**
     * @expectedException ValuSo\Exception\InvalidServiceException
     */
    public function testRegisterServiceFailsWithEmptyServiceName()
    {
        $this->serviceLoader->registerService('testid', '');
    }
    
    /**
     * @expectedException ValuSo\Exception\InvalidServiceException
     */
    public function testRegisterServiceFailsWithInvalidServiceName()
    {
        $this->serviceLoader->registerService('testid', 'aa.');
    }
    
    /**
     * @expectedException ValuSo\Exception\InvalidServiceException
     */
    public function testRegisterServiceFailsWithInvalidServiceId()
    {
        $this->serviceLoader->registerService('.', 'aa.');
    }
    
    public function testRegisterServicesUsingNameOnly()
    {
        $services  = [
            'testid' => 'testservice'
        ];
        
        $smConfig = new ServiceManagerConfig(['invokables' => ['testid' => self::CLOSURE_SERVICE_CLASS]]);
        
        $this->serviceLoader->configureServiceManager($smConfig);
        $this->serviceLoader->registerServices($services);
        
        $this->assertInstanceof(self::CLOSURE_SERVICE_CLASS, $this->serviceLoader->load('testid'));
    }
    
    public function testRegisterServicesUsingBothClassAndServiceSpecs()
    {
        $s = new ClosureService();
        
        $services  = [
            'testid' => ['name' => 'test', 'class' => self::CLOSURE_SERVICE_CLASS, 'service' => $s]
        ];
        
        $this->serviceLoader->registerServices($services);
        $this->assertSame($s, $this->serviceLoader->load('testid'));
    }
    
    public function testRegisterServicesUsingFactory()
    {
        $services  = [
            'testid' => [
                'name' => 'test', 
                'factory' => self::CLOSURE_SERVICE_FACTORY,
            ]
        ];
        
        $this->serviceLoader->registerServices($services);
        
        $this->assertInstanceOf(self::CLOSURE_SERVICE_CLASS, $this->serviceLoader->load('testid'));
    }
    
    public function testRegisterServices()
    {        
        $services  = [
            'service1' => [
                'name' => 'test', 
                'factory' => self::CLOSURE_SERVICE_FACTORY,
                'options' => ['opt1' => 'value1']
            ],
            'service2' => [
                'name' => 'test', 
                'service' => new ClosureService(function(){return 'service2';}),
                'priority' => 1000, // Execute first
            ],
            'service3' => [
                'name' => 'test',
                'service' => self::CLOSURE_SERVICE_CLASS,
                'enabled' => false
            ]
        ];
        
        $this->serviceLoader->registerServices($services);

        $cmd = new Command('test');
        $responses = $this->serviceLoader->getCommandManager()->trigger($cmd);
        
        // Assert that both services were executed
        $this->assertEquals(2, $responses->count());
        
        // Assert that services are executed in correct order (priority)
        $this->assertEquals('service2', $responses->first());
        
        // Assert that options are passed
        $this->assertEquals(['opt1' => 'value1'], $this->serviceLoader->load('service1')->config);
    }

    public function testLoadRegisteredInstance()
    {
        $c = new ClosureService();
        $this->serviceLoader->registerService('testid', 'testservice', $c);
        
        $this->assertSame($c, $this->serviceLoader->load('testid'));
    }
    
    public function testLoadIsCaseInSensitive()
    {
        $c = new ClosureService();
        $this->serviceLoader->registerService('testid', 'testservice', $c);
        
        // Does not cause an exception
        $this->assertSame($c, $this->serviceLoader->load('Testid'));
    }
    
    public function testDisableEnableService()
    {
        $c = new ClosureService();
        $this->serviceLoader->registerService('testid', 'testservice', $c);
        
        $this->assertTrue($this->serviceLoader->disableService('testid'));
        
        $cmd = new Command('testservice');
        $responses = $this->serviceLoader->getCommandManager()->trigger($cmd);
        $this->assertTrue($responses->isEmpty());
        
        $this->assertTrue($this->serviceLoader->enableService('testid'));
        $responses = $this->serviceLoader->getCommandManager()->trigger($cmd);
        $this->assertFalse($responses->isEmpty());
    }
    
    public function testEnableAlreadyEnabled()
    {
        $c = new ClosureService();
        $this->serviceLoader->registerService('testid', 'testservice', $c);
        $this->assertFalse($this->serviceLoader->enableService('testid'));
    }
    
    public function testDisableAlreadyDisabled()
    {
        $c = new ClosureService();
        $this->serviceLoader->registerService('testid', 'testservice', $c);
        $this->serviceLoader->disableService('testid');
        
        $this->assertFalse($this->serviceLoader->disableService('testid'));
    }

    public function testExists()
    {
        $c = new ClosureService();
        $this->serviceLoader->registerService('testid', 'Test.Service', $c);
        $this->assertTrue($this->serviceLoader->exists('Test.Service'));
    }

    public function testAttachListeners()
    {
        $services  = [
            'service1' => [
                'name' => 'Test.Service', 
                'service' => new ClosureService(function(){return 'service1';})
            ],
            'service2' => [
                'name' => 'Test.Service', 
                'service' => new ClosureService(function(){return 'service2';}),
            ]
        ];
        
        $this->serviceLoader->registerServices($services);

        $cmd = new Command('Test.Service', 'test');
        $responses = $this->serviceLoader->getCommandManager()->trigger($cmd);
        
        $this->assertEquals('service1', $responses->first());
        $this->assertEquals('service2', $responses->last());
    }

    public function testSetGetCache()
    {
        $cache = StorageFactory::factory(['adapter' => 'memory']);
        $this->assertEquals(
            $this->serviceLoader,
            $this->serviceLoader->setCache($cache)
        );
        
        $this->assertEquals($cache, $this->serviceLoader->getCache());
    }
    
    public function testSetOptions()
    {
        $cache     = StorageFactory::factory(['adapter' => 'memory']);
        $locator   = new ServiceManager();
        $smConfig  = new ServiceManagerConfig(['invokables' => ['testid' => self::CLOSURE_SERVICE_CLASS]]);
        $services  = ['testid' => ['name' => 'testservice']];
    
        $this->serviceLoader->setOptions(array(
                'cache' => $cache,
                'locator' => $locator,
                'service_manager' => $smConfig,
                'services' => $services
        ));
    
        $this->assertSame($cache, $this->serviceLoader->getCache());
        $this->assertSame($locator, $this->serviceLoader->getServiceLocator());
        $this->assertInstanceof(self::CLOSURE_SERVICE_CLASS, $this->serviceLoader->load('testid'));
    }
}

