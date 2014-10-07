<?php
namespace ValuSoTest\Broker;

use PHPUnit_Framework_TestCase;
use ValuSo\Broker\Worker;
use ValuSoTest\TestAsset\MockServiceBroker;
use SlmQueueTest\Asset\SimpleQueue;
use SlmQueue\Job\JobPluginManager;
use ArrayObject;

class WorkerTest 
    extends PHPUnit_Framework_TestCase
{
    
    /**
     * @var Worker
     */
    protected $worker;
    
    /**
     * @var MockServiceBroker
     */
    protected $serviceBroker;
    
    /**
     * @var string
     */
    protected $service = 'test';
    
    protected function setUp()
    {
        $this->serviceBroker = new MockServiceBroker();
        
        $this->serviceBroker->getLoader()->registerService('Test1', 'Test', function($command) {
            if ($command->getOperation() === 'count') {
                return 1;
            } else if ($command->getOperation() === 'published') {
                return false;
            }
        });
        
        $this->serviceBroker->getLoader()->registerService('Test2', 'Test', function($command) {
            if ($command->getOperation() === 'count') {
                return 2;
            } else if ($command->getOperation() === 'published') {
                return true;
            }
        });
            
        $this->serviceBroker->getLoader()->registerService('Test3', 'Test', function($command) {
            if ($command->getOperation() === 'count') {
                return 3;
            } else if ($command->getOperation() === 'published') {
                return false;
            }
        });
        
        $this->worker = new Worker($this->serviceBroker, $this->service);
    }
    
    public function testExecInContext()
    {
        $this->worker->context('test');
        $this->worker->exec('count');
        
        $this->assertEquals('test', $this->serviceBroker->lastCommand->getContext());
    }
    
    public function testExecWithIdentity()
    {
        $identity = new \ArrayObject(['id' => 'valu']);
        
        $this->worker->identity($identity);
        $this->worker->exec('count');
        
        $lastIdentity = $this->serviceBroker->lastCommand->getIdentity();
        $this->assertEquals('valu', $lastIdentity['id']);
    }
    
    public function testExecUntil()
    {
        $this->worker->until(function($response) {
            return $response === 2;
        });
        
        $responses = $this->worker->exec('count');
        $this->assertEquals(2, $responses->last());
    }
    
    public function testExecUntilTrue()
    {
        $this->worker->untilTrue();
        $responses = $this->worker->exec('published');
        $this->assertTrue($responses->last());
    }
    
    public function testExecUntilFalse()
    {
        $this->worker->untilFalse();
        $responses = $this->worker->exec('published');
        $this->assertFalse($responses->last());
    }
    
    public function testSetArgsAndExec()
    {
        $this->worker->args(['a' => 'b']);
        $this->worker->exec('count');
        $this->assertEquals('b', $this->serviceBroker->lastCommand->getParam('a'));
    }
    
    public function testExecWithArgs()
    {
        $this->worker->exec('count', ['a' => 'b']);
        $this->assertEquals('b', $this->serviceBroker->lastCommand->getParam('a'));
    }
    
    public function testExecWithArgsOverwritesPresetArgs()
    {
        $this->worker->args(['a' => 'c']);
        $this->worker->exec('count', ['a' => 'b']);
        $this->assertEquals('b', $this->serviceBroker->lastCommand->getParam('a'));
    }
    
    public function testExec()
    {
        $this->worker->args(['a' => 'c']);
        $this->worker->context('test');
        $this->worker->identity(new ArrayObject(['username' => 'valu']));
        $responses = $this->worker->exec('count');
        
        $this->assertEquals('test', $this->serviceBroker->lastCommand->getContext());
        $this->assertEquals('test', $this->serviceBroker->lastCommand->getService());
        $this->assertEquals('count', $this->serviceBroker->lastCommand->getOperation());
        $this->assertEquals(3, $responses->count());
        $this->assertEquals(1, $responses->first());
        $this->assertEquals(3, $responses->last());
    }
    
    public function testQueue()
    {
        $jpm = new JobPluginManager();
        $jpm->setInvokableClass('ValuSo\Broker\Job\ServiceJob', 'ValuSo\Broker\Job\ServiceJob');
        
        $queue = new SimpleQueue('TestQueue', $jpm);
        $this->serviceBroker->setQueue($queue);
        
        $this->worker->context('test');
        $this->worker->identity(new ArrayObject(['username' => 'valu']));
        $job = $this->worker->queue('count', ['a' => 'b'], ['ttl' => 3600]);
        
        $content = $job->getContent();
        $this->assertEquals([
            'context'   => 'test',
            'service'   => $this->service,
            'operation' => 'count',
            'params'    => ['a' => 'b'],
            'identity'  => ['username' => 'valu']
        ], $content);
        
        $this->assertEquals(['ttl' => 3600], $this->serviceBroker->queueOptions);
    }
}