<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\Trigger as TriggerAnnotation;
use ValuSo\Annotation\AnnotationBuilder;
use PHPUnit_Framework_TestCase;

/**
 * Trigger test case.
 */
class TriggerTest extends PHPUnit_Framework_TestCase
{

    /**
     *
     * @var AnnotationBuilder
     */
    private $triggerAnnotation;

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
}