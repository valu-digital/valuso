<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\AnnotationBuilder;
use ValuSo\Broker\ServiceBroker;
use PHPUnit_Framework_TestCase;

/**
 * Abstract annotation test case.
 */
abstract class AbstractTestCase extends PHPUnit_Framework_TestCase
{
    /**
     *
     * @var AnnotationBuilder
     */
    protected $annotationBuilder;
    
    /**
     *
     * @var ServiceBroker
     */
    protected $serviceBroker;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        $this->serviceBroker = new ServiceBroker();
        $this->annotationBuilder = new AnnotationBuilder();
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->annotationBuilder = null;
        $this->serviceBroker = null;
        parent::tearDown();
    }
}