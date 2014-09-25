<?php
namespace ValuSoTest\Broker;

use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Broker\ServiceBroker;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Application;
use ValuSo\Command\CommandInterface;

class SlmQueueIntegrationTest extends TestCase
{
    /**
     * @var ServiceManager
     */
    protected $sm;
    
    /**
     * @var ServiceBroker
     */
    protected $serviceBroker;
    
    /**
     * @var Application
     */
    protected static $application;
    
    protected $triggered;
    
    public $identity;
    
    public $invokedCommand;
    
    public static function setUpBeforeClass()
    {
        self::$application = Application::init([
            'modules' => [
                'SlmQueue',
                'SlmQueueBeanstalkd',
                'ValuSo',
            ],
            'module_listener_options' => [
                'config_static_paths' => [__DIR__ . '/../../../config/tests.config.php'],
                'config_cache_enabled' => false,
                'module_paths' => [
                    'vendor/valu',
                    'vendor/slm',
                ]
            ]
        ]);
    }
    
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        $this->triggered = new \ArrayObject();
    
        $this->sm = self::$application->getServiceManager();
        $this->serviceBroker = $this->sm->get('ServiceBroker');
        
        $this->clearQueue();
        
        $this->identity = $identity = new \ArrayObject([
            'id' => 'b0532c8099953acabc060770eb296f86',
            'superuser' => false,
            'username' => 'admin',
            'roles' => ['/' => 'member']
        ]);
    
        $this->serviceBroker->setDefaultIdentity($this->identity);
        
        $self = $this;
        $this->serviceBroker->getLoader()->registerService('User', 'User', function (CommandInterface $command) use($self) {
            if ($command->getOperation() === 'resolveIdentity') {
                return $self->identity;
            }
        });
        
        $this->serviceBroker->getLoader()->registerService('Identity', 'Identity', function (CommandInterface $command) use($self) {
            if ($command->getOperation() === 'setIdentity') {
                return $self->identity;
            }
        });
        
        $this->serviceBroker->getLoader()->registerService('Test', 'Test', function($command) use($self) {
            if ($command->getOperation() === 'run') {
                $self->invokedCommand = $command;
            }
        });
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->clearQueue();
        $this->sm = null;
        $this->serviceBroker = null;
        $this->invokedCommand = null;
    
        gc_collect_cycles();
    
        parent::tearDown();
    }
    
    protected function clearQueue()
    {
        $pheanstalk = $this->sm->get('SlmQueueBeanstalkd\Service\PheanstalkService');
        
        try
        {
            while($job = $pheanstalk->peekReady('valu_so'))
            {
                $pheanstalk->delete($job);
            }
        }
        catch(\Pheanstalk_Exception_ServerException $e){}
    }
    
    public function testQueue()
    {
        $job = $this->serviceBroker
            ->service('Test')
            ->queue('run', ['now' => true]);
        
        $worker = $this->sm->get('SlmQueueBeanstalkd\Worker\BeanstalkdWorker');
        $worker->processQueue('valu_so');
        
        $this->assertInstanceOf('ValuSo\Queue\Job\ServiceJob', $job);
        $this->assertNotNull($this->invokedCommand);
        $this->assertEquals('run', $this->invokedCommand->getOperation());
        $this->assertEquals(['now' => true], $this->invokedCommand->getParams()->getArrayCopy());
        $this->assertEquals($this->identity->getArrayCopy(), $this->invokedCommand->getIdentity()->getArrayCopy());
    }
    
    public function testQueueWithHigherPriority()
    {
        $job1 = $this->serviceBroker
            ->service('Test')
            ->queue('run', ['first' => false], ['priority' => 1000]);
    
        $job2 = $this->serviceBroker
            ->service('Test')
            ->queue('run', ['first' => true], ['priority' => 0]);
    
        $job3 = $this->serviceBroker
            ->service('Test')
            ->queue('run', ['first' => false], ['priority' => 1050]);
    
        $worker = $this->sm->get('SlmQueueBeanstalkd\Worker\BeanstalkdWorker');
        $worker->processQueue('valu_so');
    
        $this->assertEquals(['first' => true], $this->invokedCommand->getParams()->getArrayCopy());
    }
    
    public function testQueueDelayed()
    {
        $time = microtime(true);
        
        $job = $this->serviceBroker
            ->service('Test')
            ->queue('run', null, ['priority' => 1024, 'delay' => 2]);
        
        $worker = $this->sm->get('SlmQueueBeanstalkd\Worker\BeanstalkdWorker');
        $worker->processQueue('valu_so');
        
        $expected = $time+1.5;
        $actual = microtime(true);
        
        $this->assertGreaterThan($expected, $actual);
    }
    
    public function testQueueJobTimeout()
    {
        $time = microtime(true);
        
        $this->serviceBroker->getLoader()->registerService('timeout', 'timeout', function($command) {
            sleep(2);
            return true;
        });
    
        $job = $this->serviceBroker
            ->service('timeout')
            ->queue('run', null, ['priority' => 1024, 'ttr' => 1]);
    
        $worker = $this->sm->get('SlmQueueBeanstalkd\Worker\BeanstalkdWorker');
        $worker->processQueue('valu_so');
    
    }
}