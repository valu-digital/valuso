<?php
namespace ValuSoTest\Broker;

use ValuSoTest\TestAsset\Annotation\AnnotationListener;
use Zend\ServiceManager\ServiceManager;
use ValuSo\Annotation\AnnotationBuilderFactory;
use PHPUnit_Framework_TestCase;

/**
 * AnnotationBuilder test case.
 */
class AnnotationBuilderFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     *
     * @var AnnotationBuilder
     */
    private $annotationBuilderFactory;
    
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        $this->annotationBuilderFactory = new AnnotationBuilderFactory();
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->annotationBuilder = null;
        parent::tearDown();
    }
    
    public function testCreateEmptyAnnotationBuilder()
    {
        $sm = $this->configureServiceManager();
        
        $this->assertInstanceOf(
            'ValuSo\Annotation\AnnotationBuilder', 
            $this->annotationBuilderFactory->createService($sm));
    }
    
    public function testCreateAnnotationBuilderWithAnnotationAndListenerAggregate()
    {
        $sm = $this->configureServiceManager(
            ['ValuSoTest\TestAsset\Annotation\Test'],
            [new AnnotationListener()]
        );
        
        $annotationBuilder = $this->annotationBuilderFactory->createService($sm);
        $spec = $annotationBuilder->getServiceSpecification('ValuSoTest\TestAsset\CustomAnnotationService');
        
        $this->assertArrayHasKey('operation1', $spec['operations']);
        $this->assertEquals('OK', $spec['operations']['operation1']['test']);
    }

    public function testCreateAnnotationBuilderWithAnnotationAndListenerAggregateUsingServiceId()
    {
        $sm = $this->configureServiceManager(
            ['ValuSoTest\TestAsset\Annotation\Test'],
            ['TestAnnotationListener']
        );
        
        $sm->setService('TestAnnotationListener', new AnnotationListener());
        $listener = $sm->get('TestAnnotationListener');
    
        $annotationBuilder = $this->annotationBuilderFactory->createService($sm);
        $spec = $annotationBuilder->getServiceSpecification('ValuSoTest\TestAsset\CustomAnnotationService');
        
        $this->assertArrayHasKey('operation1', $spec['operations']);
        $this->assertEquals('OK', $spec['operations']['operation1']['test']);
    }

    private function configureServiceManager($annotations = array(), $listeners = array())
    {
        $sm = new ServiceManager();
        $sm->setService('Config', [
            'valu_so' => [
                'annotations' => $annotations,
                'annotation_listeners' => $listeners
            ]
        ]);
        
        return $sm;
    }
}