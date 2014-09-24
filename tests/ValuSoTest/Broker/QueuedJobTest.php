<?php
namespace ValuSoTest\Broker;

use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Broker\QueuedJob;
use ValuSo\Broker\ServiceBroker;
use Zend\ServiceManager\ServiceManager;
use ValuSo\Command\Command;

class QueuedJobTest extends TestCase
{
    
    private $queuedJob;
    
    private $testCommand;
    
    private $testContent = [
            'service' => 'test', 
            'operation' => 'test',
            'params' => [],
            'context' => Command::CONTEXT_NATIVE,
            'identity' => ['id' => 'abc'],
            'callback' => null
        ];
    
    private $serviceBroker;
    
    public $testOperationInvoked = false;
    
    public $testCallbackInvoked = false;
    
    public $identityUsed;
    
    public $resolvedIdentity = ['id' => 'abc'];
    
    protected function setUp()
    {
        $this->serviceBroker = $broker = new ServiceBroker();
        
        $self = $this;
        $broker->getLoader()->registerService('test', 'test', function(Command $command) use($self) {
            if ($command->getOperation() === 'test') {
                $self->identityUsed = $command->getIdentity();
                $self->testOperationInvoked = true;
                return true;
            }
            
            return false;
        });
        
        $broker->getLoader()->registerService('user', 'user', function(Command $command) use($self) {
            if ($command->getOperation() === 'resolveIdentity' && $command->getParam(0) === 'testuser') {
                return $self->resolvedIdentity;
            }
        
            return false;
        });
        
        $broker->getLoader()->registerService('identity', 'identity', function(Command $command) {
            if ($command->getOperation() === 'setIdentity') {
                return new \ArrayObject($command->getParam(0));
            } else if ($command->getOperation() === 'getIdentity') {
                return new \ArrayObject();
            }
        });
        
        $this->testContent['callback'] = function() use($self) {
            $self->testCallbackInvoked = true;
        };
        
        $this->testCommand = $command = new Command(
            $this->testContent['service'], 
            $this->testContent['operation'], 
            $this->testContent['params'], 
            $this->testContent['context']);
        
        $this->queuedJob = new QueuedJob();
        $this->queuedJob->setup($command, $this->testContent['identity'], $this->testContent['callback']);
        $this->queuedJob->setServiceBroker($broker);
    }
    
    /**
     * @expectedException RuntimeException
     */
    public function testSetupFailsWithoutIdentity()
    {
        $this->queuedJob = new QueuedJob();
        $this->queuedJob->setup($this->testCommand, null, null);
    }
    
    public function testExecuteInvokesCorrectOperation()
    {
        $this->queuedJob->execute();
        $this->assertTrue($this->testOperationInvoked);
    }
    
    public function testExecuteInvokesCorrectCallback()
    {
        $this->queuedJob->execute();
        $this->assertTrue($this->testCallbackInvoked);
    }
    
    public function testExecuteRefreshesIdentity()
    {
        $identity = new \ArrayObject([
            'username' => 'testuser'
        ]);
        
        $this->queuedJob = new QueuedJob();
        $this->queuedJob->setup($this->testCommand, $identity, $this->testContent['callback']);
        $this->queuedJob->setServiceBroker($this->serviceBroker);
        $this->queuedJob->execute();
        
        $this->assertEquals($this->resolvedIdentity, $this->identityUsed->getArrayCopy());
    }
    
    /**
     * @expectedException RuntimeException
     */
    public function testExecuteWithInvalidIdentity()
    {
        $identity = new \ArrayObject([
            'username' => 'notexistinguser'
        ]);
        
        $this->queuedJob = new QueuedJob();
        $this->queuedJob->setup($this->testCommand, $identity, $this->testContent['callback']);
        $this->queuedJob->setServiceBroker($this->serviceBroker);
        $this->queuedJob->execute();
        
        $this->assertEquals($this->resolvedIdentity, $this->identityUsed->getArrayCopy());
    }
    
    public function testGetCommand()
    {
        $command = $this->queuedJob->getCommand();
        
        $this->assertInstanceOf('ValuSo\Command\Command', $command);
        $this->assertEquals($this->testContent['service'], $command->getService());
        $this->assertEquals($this->testContent['operation'], $command->getOperation());
        $this->assertEquals($this->testContent['params'], $command->getParams()->getArrayCopy());
        $this->assertInstanceof('ArrayObject', $command->getIdentity());
        $this->assertEquals($this->testContent['identity'], $command->getIdentity()->getArrayCopy());
    }

    public function testGetCallback()
    {
        $this->assertInternalType('object', $this->queuedJob->getCallback());
    }
   
    public function testSetGetServiceBroker()
    {
        $broker = new ServiceBroker();
        $this->queuedJob->setServiceBroker($broker);
        $this->assertSame($broker, $this->queuedJob->getServiceBroker());
    }
    
    public function testServiceBrokerFetchedViaServiceLocator()
    {
        $broker = new ServiceBroker();
        $sm = new ServiceManager();
        $sm->setService('ServiceBroker', $broker);
        
        $queuedJob = new QueuedJob();
        $queuedJob->setup($this->testCommand, $this->testContent['identity']);
        $queuedJob->setServiceLocator($sm);
        $this->assertSame($broker, $queuedJob->getServiceBroker());
    }
    
    public function testSetGetServiceLocator()
    {
        $sm = new ServiceManager();
        $this->queuedJob->setServiceLocator($sm);
        $this->assertSame($sm, $this->queuedJob->getServiceLocator());
    }
    
    /**
     * @expectedException RuntimeException
     */
    public function testExecuteFailsIfBrokerNotPresent()
    {
        $queuedJob = new QueuedJob();
        $queuedJob->setup($this->testCommand, $this->testContent['identity']);
        $queuedJob->execute();
    }
}