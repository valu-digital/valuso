<?php
namespace ValuSoTest\Broker;

use ValuSo\Broker\ServiceEvent;
use ValuSo\Broker\ServiceBroker;
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
 * ServiceBroker test case.
 */
class ServiceBrokerTest extends PHPUnit_Framework_TestCase
{
    
    const CLOSURE_SERVICE_CLASS = 'ValuSoTest\TestAsset\ClosureService';
    
    const CLOSURE_SERVICE_FACTORY = 'ValuSoTest\TestAsset\ClosureServiceFactory';
    
    /**
     *
     * @var ServiceBroker
     */
    private $serviceBroker;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->serviceBroker = new ServiceBroker();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->serviceBroker = null;
        
        parent::tearDown();
    }

    /**
     * Tests ServiceBroker->getDefaultContext()
     */
    public function testGetDefaultContext()
    {
        $this->assertEquals(
            Command::CONTEXT_NATIVE,
            $this->serviceBroker->getDefaultContext());
    }
    
    /**
     * Tests ServiceBroker->setDefaultContext()
     */
    public function testSetGetDefaultContext()
    {
        $this->assertSame(
                $this->serviceBroker,
                $this->serviceBroker->setDefaultContext(Command::CONTEXT_HTTP));
        
        $this->assertEquals(
                Command::CONTEXT_HTTP,
                $this->serviceBroker->getDefaultContext());
    }

    public function testSetGetLoader()
    {
        $loader = new ServiceLoader();
        
        $this->assertSame(
                $this->serviceBroker,
                $this->serviceBroker->setLoader($loader));
        
        $this->assertSame(
                $loader,
                $this->serviceBroker->getLoader());
    }

    public function testGetEventManager()
    {
        $this->assertInstanceOf('\Zend\EventManager\EventManager', $this->serviceBroker->getEventManager());
    }

    public function testExists()
    {
        $c = new ClosureService();
        $loader = new ServiceLoader();
        $loader->registerService('testid', 'Test.Service', $c);
        
        $this->serviceBroker->setLoader($loader);
        
        $this->assertTrue($this->serviceBroker->exists('Test.Service'));
    }

    public function testService()
    {
        $this->serviceBroker->getLoader()->registerService(
            'testid', 'Test.Service', self::CLOSURE_SERVICE_CLASS);
        
        $this->assertInstanceOf(
                'ValuSo\Broker\Worker',
                $this->serviceBroker->service('Test.Service'));
    }

    public function testExecute()
    {
        $this->serviceBroker->getLoader()->registerService(
            'TestService', 'Test.Service', new ClosureService(
                    function($command){return $command->getOperation() == 'done' ? $command->getParam(0) : false;}));
        
        $this->serviceBroker->getLoader()->registerService(
            'OtherService', 'Test.Service', self::CLOSURE_SERVICE_CLASS);
        
        $result = $this->serviceBroker
            ->execute('Test.Service', 'done', ['yes'], function(){return true;})->last();
        
        $this->assertEquals(
            'yes', $result);
    }

    public function testExecuteInContext()
    {
        $this->serviceBroker->getLoader()->registerService(
            'FirstService', 'Test.Service', new ClosureService(
                function($command){return $command->getContext();}));
        
        $this->serviceBroker->getLoader()->registerService(
            'SecondService', 'Test.Service', new ClosureService(
                function($command){return $command->getOperation() == 'done' ? $command->getParam(0) : false;}));
        
        $this->serviceBroker->getLoader()->registerService(
            'ThirdService', 'Test.Service', self::CLOSURE_SERVICE_CLASS);
        
        // Execute until reponse is 'yes'
        $responses = $this->serviceBroker
            ->executeInContext(
                'http', 
                'Test.Service', 
                'done', 
                ['yes'], 
                function($response){if($response == 'yes') return true;});
        
        $this->assertEquals(
            'http', $responses->first());
        
        $this->assertEquals(
            'yes', $responses->last());
    }

    public function testQueue()
    {
        // TODO Auto-generated ServiceBrokerTest->testQueue()
        $this->markTestIncomplete("queue test not implemented");
        
        $this->serviceBroker->queue(/* parameters */);
    }

    public function testGetQueue()
    {
        // TODO Auto-generated ServiceBrokerTest->testGetQueue()
        $this->markTestIncomplete("getQueue test not implemented");
        
        $this->serviceBroker->getQueue(/* parameters */);
    }
    
    public function testSetOptions()
    {
        $loader = new ServiceLoader();

        $this->serviceBroker->setOptions(['loader' => $loader]);
        $this->assertSame(
                $loader,
                $this->serviceBroker->getLoader());
    }

    public function testDispatch()
    {
        $command = new Command('Valu.Test', 'run', ['all' => true], Command::CONTEXT_CLI);
        
        $this->serviceBroker->getLoader()->registerService(
            'ValuTests', 'Valu.Test', new ClosureService(
                    function($command){return $command;}));
        
        $this->assertSame(
            $command,
            $this->serviceBroker->dispatch($command)->last());
    }
    
    public function testDispatchTriggersInitEvent()
    {
        $command = new Command('Valu.Test', 'run', ['all' => true], Command::CONTEXT_CLI);
        
        $this->serviceBroker->getLoader()->registerService(
                'ValuTests', 'Valu.Test', self::CLOSURE_SERVICE_CLASS);
        
        $triggered = false;
        $class = null;
        
        $this->serviceBroker
            ->getEventManager()
            ->attach('init.valu.test.run', 
                    function(ServiceEvent $e) use($triggered, &$class) {$class = get_class($e);});
        
        $this->serviceBroker->dispatch($command);
        
        $this->assertNotNull($class, "Failed asserting that event 'init.valu.test' was triggered");
        $this->assertEquals('ValuSo\Broker\ServiceEvent', $class, 'Failed asserting that Broker dispatches correct event');
    }
    
    /**
     * @expectedException ValuSo\Exception\ServiceNotFoundException
     */
    public function testDispatchFailsIfServiceDoesNotExist()
    {
        $command = new Command('Valu.Test', 'run', ['all' => true], Command::CONTEXT_CLI);
        $this->serviceBroker->dispatch($command);
    }
    
    public function testFalseResponseByInitEventListenerStopsServiceExecution()
    {
        $command = new Command('Valu.Test', 'run', ['all' => true], Command::CONTEXT_CLI);
        
        $this->serviceBroker->getLoader()->registerService(
                'ValuTests', 'Valu.Test', self::CLOSURE_SERVICE_CLASS);

        $this->serviceBroker
            ->getEventManager()
            ->attach('init.valu.test.run',
                    function(ServiceEvent $e) {return false;});
        
        $responses = $this->serviceBroker->dispatch($command);
        
        $this->assertEquals(0, $responses->count());
        $this->assertTrue($responses->stopped());
    }
    
    public function testInitEventListenerCanManipulateCommandParams()
    {
        $command = new Command('Valu.Test', 'run', ['level' => 'all'], Command::CONTEXT_CLI);
        
        $this->serviceBroker->getLoader()->registerService(
                'ValuTests', 'Valu.Test', new ClosureService(function($command){return $command->getParam('level');}));
        
        $this->serviceBroker
            ->getEventManager()
            ->attach('init.valu.test.run',
                    function(ServiceEvent $e) {$e->setParam('level', 'none');});
        
        
        $this->assertEquals(
            'none',
            $this->serviceBroker->dispatch($command)->first());
    }
    
    public function testDispatchTriggersFinalEvent()
    {
        $command = new Command('Valu.Test', 'run', ['all' => true], Command::CONTEXT_CLI);
    
        $this->serviceBroker->getLoader()->registerService(
            'ValuTests', 'Valu.Test', self::CLOSURE_SERVICE_CLASS);
    
        $triggered = false;
        $class = null;
    
        $this->serviceBroker
        ->getEventManager()
        ->attach('final.valu.test.run',
                function(ServiceEvent $e) use($triggered, &$class) {$class = get_class($e);});
    
        $this->serviceBroker->dispatch($command);
    
        $this->assertNotNull($class, "Failed asserting that event 'final.valu.test' was triggered");
        $this->assertEquals('ValuSo\Broker\ServiceEvent', $class, 'Failed asserting that Broker dispatches correct event');
    }
}

