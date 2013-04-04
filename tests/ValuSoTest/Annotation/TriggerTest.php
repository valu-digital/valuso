<?php
namespace ValuSoTest\Annotation;

use ValuSo\Command\Command;

use ValuSo\Annotation\Trigger as TriggerAnnotation;
use ValuSo\Annotation\AnnotationBuilder;
use PHPUnit_Framework_TestCase;

/**
 * Trigger test case.
 */
class TriggerTest extends AbstractTestCase
{

    /**
     *
     * @var AnnotationBuilder
     */
    private $triggerAnnotation;
    
    public function setUp()
    {
        parent::setUp();
        
        $this->serviceProxy = $this->generateProxyService([
            'operations' => [
                'operation1' => [
                    'events' => [['type' => 'pre', 'name' => 'preevent', 'args' => null]]
                ],
                'operation2' => [
                    'events' => [['type' => 'post', 'name' => 'postevent', 'args' => null]]
                ]
            ]        
        ]);
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->triggerAnnotation = null;
        parent::tearDown();
    }
    
    public function testConstructAndGet()
    {
        $this->triggerAnnotation = new TriggerAnnotation(['value' => 'pre']);
        $this->assertEquals('pre', $this->triggerAnnotation->getTrigger());
    }
    
    public function testGetEventDescriptionFromString()
    {
        $this->triggerAnnotation = new TriggerAnnotation(['value' => 'pre']);
        $this->assertEquals(
                ['type' => 'pre', 'name' => null, 'args' => null], 
                $this->triggerAnnotation->getEventDescription());
    }
    
    public function testGetEventDescriptionFromArray()
    {
        $specs = ['type' => 'pre', 'name' => 'eventname', 'args' => ['arg1' => 'value1']];
        
        $this->triggerAnnotation = new TriggerAnnotation(['value' => $specs]);
        $this->assertEquals(
                $specs, 
                $this->triggerAnnotation->getEventDescription());
    }
    
    /**
     * Integration test
     */
    public function testServiceTriggersPreEvent()
    {
        $command = new Command();
        $command->setOperation('operation1');
        $command->setParam('returnValue', true);
        $command->setContext('native');
        
        $triggered = false;
        
        $events = $this->serviceProxy->getEventManager();
        $events->attach('*', function($event) use(&$triggered) {
            if ($event->getName() === 'preevent') {
                $triggered = true;
            }
        });
        
        $this->serviceProxy->__invoke($command);
        $this->assertTrue($triggered);
    }
    
    /**
     * Integration test
     */
    public function testServiceTriggersPostEvent()
    {
        $command = new Command();
        $command->setOperation('operation2');
        $command->setParam('returnValue', true);
        $command->setContext('native');
        
        $triggered = false;
        
        $events = $this->serviceProxy->getEventManager();
        $events->attach('*', function($event) use(&$triggered) {
            if ($event->getName() === 'postevent') {
                $triggered = true;
            }
        });
        
        $this->serviceProxy->__invoke($command);
        $this->assertTrue($triggered);
    }
}