<?php
namespace ValuSoTest\Command;

use ValuSo\Command\Command;
use ValuSo\Exception\SkippableException;
use ValuSoTest\TestAsset\ClosureService;
use PHPUnit_Framework_TestCase;
use ValuSo\Command\CommandManager;

/**
 * CommandManager test case.
 */
class CommandManagerTest extends PHPUnit_Framework_TestCase
{

    /**
     *
     * @var CommandManager
     */
    private $commandManager;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        $this->commandManager = new CommandManager();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        // TODO Auto-generated CommandManagerTest::tearDown()
        $this->commandManager = null;
        
        parent::tearDown();
    }
    
    public function testAttach()
    {
        $s1 = new ClosureService();
        $listener = $this->commandManager->attach('test', $s1, 1000);
        
        $this->assertInstanceOf('ValuSo\Command\LazyCallbackHandler', $listener);
        $this->assertSame($s1, $listener->getCallback());
        $this->assertEquals(1000,$listener->getMetadatum('priority'));
    }
    
    public function testDetach()
    {
        $s1 = new ClosureService();
        $cmd = new Command('test');
        
        $listener = $this->commandManager->attach('test', $s1, 1000);
        $this->assertFalse($this->commandManager->trigger($cmd)->isEmpty());
        $this->assertTrue($this->commandManager->detach($listener));
        $this->assertTrue($this->commandManager->trigger($cmd)->isEmpty());
    }
    
    public function testServiceIsExcecuted()
    {
        $s1 = new ClosureService(function(){return 'executed';});
        $this->commandManager->attach('test', $s1);
        
        $cmd = new Command('test', 'test');
        $responses = $this->commandManager->trigger($cmd);
        
        $this->assertEquals(
            'executed',
            $responses->last());
    }
    
    public function testServiceNameIsCaseInsensitiveWhenExecuted()
    {
        $s1 = new ClosureService(function(){return 'executed';});
        $this->commandManager->attach('test', $s1);
        
        $cmd = new Command('Test');
        $responses = $this->commandManager->trigger($cmd);
        
        $this->assertEquals(
            'executed',
            $responses->last());
    }
    
    public function testCorrectServiceIsExcecuted()
    {
        $s1 = new ClosureService(function(){return 'first';});
        $s2 = new ClosureService(function(){return 'second';});
        $this->commandManager->attach('test1', $s1);
        $this->commandManager->attach('test2', $s2);
    
        $cmd = new Command('test1', 'test');
        $responses = $this->commandManager->trigger($cmd);
    
        $this->assertEquals(
            'first',
            $responses->first());
        
        $this->assertEquals(
            'first',
            $responses->last());
    }
    
    public function testLazyServiceIsExcetuted()
    {
        $this->commandManager->attach('Test.Service', 'testid');
        $this->commandManager->getServiceLoader()->registerService(
                'testid', 
                'Test.Service', 
                new ClosureService(function($c){return $c->getOperation() == 'run' ? 'executed' : null;}));
        
        $cmd = new Command('Test.Service', 'run');
        $responses = $this->commandManager->trigger($cmd);
        
        $this->assertEquals(
            'executed',
            $responses->last());
    }
    
    public function testMultipleServicesAreExecuted()
    {
        $s1 = new ClosureService(function(){return 'first';});
        $s2 = new ClosureService(function(){return 'last';});
        $this->commandManager->attach('test', $s1);
        $this->commandManager->attach('test', $s2);
        
        $cmd = new Command('test', 'test');
        $responses = $this->commandManager->trigger($cmd);
        
        $this->assertEquals(
                'first',
                $responses->first());
        
        $this->assertEquals(
                'last',
                $responses->last());
    }
    
    public function testCommandIsPassedToService()
    {
        $s1 = new ClosureService(function($command){return $command;});
        
        $cmd = new Command('test', 'test');
        
        $cb = $this->commandManager->attach('test', $s1);
        $responses = $this->commandManager->trigger($cmd);
        
        $this->assertSame(
            $cmd,
            $responses->first());
    }
    
    public function testExecutionStopsWhenPropagationIsStopped()
    {
        $s1 = new ClosureService(function($command){$command->stopPropagation(); return 'first';});
        $s2 = new ClosureService(function(){return 'last';});
        $this->commandManager->attach('test', $s1);
        $this->commandManager->attach('test', $s2);
        
        $cmd = new Command('test', 'test');
        $responses = $this->commandManager->trigger($cmd);
        
        $this->assertEquals(
                'first',
                $responses->last());
        
        $this->assertTrue($responses->stopped());
    }

    public function testTriggerContinuesWithSkippableException()
    {
        $s1 = new ClosureService(function(){throw new SkippableException('Skippable');});
        $s2 = new ClosureService(function(){return null;});
        
        $this->commandManager->attach('test', $s1);
        $this->commandManager->attach('test', $s2);
        
        $cmd = new Command('test', 'test');
        $responses = $this->commandManager->trigger($cmd);
    }
    
    /**
     * @expectedException ValuSo\Exception\SkippableException
     */
    public function testTriggerFailsWithSkippableExceptionAndEmptyResponses()
    {
        $s1 = new ClosureService(function(){throw new SkippableException('Skippable');});
        $this->commandManager->attach('test', $s1);
        
        $cmd = new Command('test', 'test');
        $responses = $this->commandManager->trigger($cmd);
    }
    
    /**
     * @expectedException \Exception
     */
    public function testTriggerThrowsNonServiceException()
    {
        $s1 = new ClosureService(function(){throw new \Exception('DIE!');});
        $s2 = new ClosureService(function(){return true;});
        
        $this->commandManager->attach('test', $s1);
        
        $cmd = new Command('test', 'test');
        $responses = $this->commandManager->trigger($cmd);
    }
}

