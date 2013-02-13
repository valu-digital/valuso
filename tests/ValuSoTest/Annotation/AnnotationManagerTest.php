<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\AnnotationManager;
use PHPUnit_Framework_TestCase;

/**
 * AnnotationManager test case.
 */
class AnnotationManagerTest extends PHPUnit_Framework_TestCase
{

    /**
     *
     * @var AnnotationManager
     */
    private $annotationManager;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->annotationManager = new AnnotationManager();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        // TODO Auto-generated AnnotationManagerTest::tearDown()
        $this->annotationManager = null;
        
        parent::tearDown();
    }

    /**
     * Constructs the test case.
     */
    public function __construct()
    {
        // TODO Auto-generated constructor
    }

    /**
     * Tests AnnotationManager->setEventManager()
     */
    public function testSetEventManager()
    {
        // TODO Auto-generated AnnotationManagerTest->testSetEventManager()
        $this->markTestIncomplete("setEventManager test not implemented");
        
        $this->annotationManager->setEventManager(/* parameters */);
    }

    /**
     * Tests AnnotationManager->getEventManager()
     */
    public function testGetEventManager()
    {
        // TODO Auto-generated AnnotationManagerTest->testGetEventManager()
        $this->markTestIncomplete("getEventManager test not implemented");
        
        $this->annotationManager->getEventManager(/* parameters */);
    }

    /**
     * Tests AnnotationManager->attach()
     */
    public function testAttach()
    {
        // TODO Auto-generated AnnotationManagerTest->testAttach()
        $this->markTestIncomplete("attach test not implemented");
        
        $this->annotationManager->attach(/* parameters */);
    }

    /**
     * Tests AnnotationManager->createAnnotation()
     */
    public function testCreateAnnotation()
    {
        // TODO Auto-generated AnnotationManagerTest->testCreateAnnotation()
        $this->markTestIncomplete("createAnnotation test not implemented");
        
        $this->annotationManager->createAnnotation(/* parameters */);
    }
}

