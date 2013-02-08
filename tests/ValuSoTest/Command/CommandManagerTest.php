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
    
    public function testServiceIsExcecuted()
    {
        $s1 = new ClosureService(function(){return 'executed';});
        $this->commandManager->attach('test', $s1);
        $responses = $this->commandManager->trigger('test');
        
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
        
        $responses = $this->commandManager->trigger('test');
        
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
        
        $command = new Command('test');
        
        $cb = $this->commandManager->attach('test', $s1);
        $responses = $this->commandManager->trigger($command);
        
        $this->assertSame(
            $command,
            $responses->first());
    }
    
    public function testExecutionStopsWhenPropagationIsStopped()
    {
        $s1 = new ClosureService(function($command){$command->stopPropagation(); return 'first';});
        $s2 = new ClosureService(function(){return 'last';});
        $this->commandManager->attach('test', $s1);
        $this->commandManager->attach('test', $s2);
        
        $responses = $this->commandManager->trigger('test');
        
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
        
        $responses = $this->commandManager->trigger('test');
    }
    
    /**
     * @expectedException ValuSo\Exception\SkippableException
     */
    public function testTriggerFailsWithSkippableExceptionAndEmptyResponses()
    {
        $s1 = new ClosureService(function(){throw new SkippableException('Skippable');});
        $this->commandManager->attach('test', $s1);
        $responses = $this->commandManager->trigger('test');
    }
    
    /**
     * @expectedException \Exception
     */
    public function testTriggerThrowsNonServiceException()
    {
        $s1 = new ClosureService(function(){throw new \Exception('DIE!');});
        $s2 = new ClosureService(function(){return true;});
        
        $this->commandManager->attach('test', $s1);
        $responses = $this->commandManager->trigger('test');
    }
}

