<?php
namespace ValuSoTest\Broker;

use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Queue\Job\ServiceJob;
use ValuSo\Broker\ServiceBroker;
use Zend\ServiceManager\ServiceManager;
use ValuSo\Command\Command;

class ServiceJobTest extends TestCase
{
    
    private $serviceJob;
    
    private $testCommand;
    
    private $testContent = [
        'service'   => 'test', 
        'operation' => 'test',
        'params'    => [],
        'context'   => Command::CONTEXT_NATIVE,
        'identity'  => ['id' => 'abc'],
    ];
    
    private $serviceBroker;
    
    public $testOperationInvoked = false;
    
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
        
        $this->testCommand = $command = new Command(
            $this->testContent['service'], 
            $this->testContent['operation'], 
            $this->testContent['params'], 
            $this->testContent['context']);
        
        $this->serviceJob = new ServiceJob();
        $this->serviceJob->setup($command, $this->testContent['identity']);
        $this->serviceJob->setServiceBroker($broker);
    }
    
    /**
     * @expectedException RuntimeException
     */
    public function testSetupFailsWithoutIdentity()
    {
        $this->serviceJob = new ServiceJob();
        $this->serviceJob->setup($this->testCommand, null, null);
    }
    
    public function testExecuteInvokesCorrectOperation()
    {
        $this->serviceJob->execute();
        $this->assertTrue($this->testOperationInvoked);
    }
    
    public function testExecuteRefreshesIdentity()
    {
        $identity = new \ArrayObject([
            'username' => 'testuser'
        ]);
        
        $this->serviceJob = new ServiceJob();
        $this->serviceJob->setup($this->testCommand, $identity);
        $this->serviceJob->setServiceBroker($this->serviceBroker);
        $this->serviceJob->execute();
        
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
        
        $this->serviceJob = new ServiceJob();
        $this->serviceJob->setup($this->testCommand, $identity);
        $this->serviceJob->setServiceBroker($this->serviceBroker);
        $this->serviceJob->execute();
        
        $this->assertEquals($this->resolvedIdentity, $this->identityUsed->getArrayCopy());
    }
    
    public function testGetCommand()
    {
        $command = $this->serviceJob->getCommand();
        
        $this->assertInstanceOf('ValuSo\Command\Command', $command);
        $this->assertEquals($this->testContent['service'], $command->getService());
        $this->assertEquals($this->testContent['operation'], $command->getOperation());
        $this->assertEquals($this->testContent['params'], $command->getParams()->getArrayCopy());
        $this->assertInstanceof('ArrayObject', $command->getIdentity());
        $this->assertEquals($this->testContent['identity'], $command->getIdentity()->getArrayCopy());
    }

    public function testSetGetServiceBroker()
    {
        $broker = new ServiceBroker();
        $this->serviceJob->setServiceBroker($broker);
        $this->assertSame($broker, $this->serviceJob->getServiceBroker());
    }
    
    public function testServiceBrokerFetchedViaServiceLocator()
    {
        $broker = new ServiceBroker();
        $sm = new ServiceManager();
        $sm->setService('ServiceBroker', $broker);
        
        $ServiceJob = new ServiceJob();
        $ServiceJob->setup($this->testCommand, $this->testContent['identity']);
        $ServiceJob->setServiceLocator($sm);
        $this->assertSame($broker, $ServiceJob->getServiceBroker());
    }
    
    public function testSetGetServiceLocator()
    {
        $sm = new ServiceManager();
        $this->serviceJob->setServiceLocator($sm);
        $this->assertSame($sm, $this->serviceJob->getServiceLocator());
    }
    
    /**
     * @expectedException RuntimeException
     */
    public function testExecuteFailsIfBrokerNotPresent()
    {
        $ServiceJob = new ServiceJob();
        $ServiceJob->setup($this->testCommand, $this->testContent['identity']);
        $ServiceJob->execute();
    }
}