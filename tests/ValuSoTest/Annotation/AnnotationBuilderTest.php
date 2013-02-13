<?php
namespace ValuSoTest\Annotation;

use Zend\Code\Annotation\AnnotationManager;

use ValuSoTest\TestAsset\TestService;
use ValuSo\Annotation\AnnotationBuilder;
use PHPUnit_Framework_TestCase;
use ArrayObject;

/**
 * AnnotationManager test case.
 */
class AnnotationManagerTest extends PHPUnit_Framework_TestCase
{

    /**
     *
     * @var AnnotationBuilder
     */
    private $annotationBuilder;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->annotationBuilder = new AnnotationBuilder();
    }
    
    public function testGetSetAnnotationManager()
    {
        $am = new AnnotationManager();
        $this->annotationBuilder->setAnnotationManager($am);
        $this->assertSame($am, $this->annotationBuilder->getAnnotationManager());
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->annotationBuilder = null;
        parent::tearDown();
    }

    public function testGetServiceVersionSpecification()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
        $specs = $specs->getArrayCopy();
        
        $this->assertArrayHasKey('version', $specs);
        $this->assertEquals('1.0', $specs['version']);
    }
    
    public function testGetOperationLevelServiceSpecification()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
        
        $this->assertArrayHasKey('operations', $specs);
        $this->assertInstanceOf('\ArrayObject', $specs['operations']);
        $this->assertInstanceOf('\ArrayObject', $specs['operations']['run']);
        
        $this->assertEquals(
            ['events' => [
                ['type' => 'pre', 'name' => null, 'args' => null],    
                ['type' => 'post', 'name' => 'post.<service>.run', 'args' => ['job', 'delayed']],   
            ], 'context' => '*'],
            $specs['operations']['run']->getArrayCopy()
        );
    }
    
    public function testGetDefaultTriggerPreAndPost()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
        $this->assertNotNull($specs['operations']['deleteAll']['events']);
    
        $this->assertEquals(
            [
                ['type' => 'pre', 'name' => null, 'args' => null],
                ['type' => 'post', 'name' => null, 'args' => null],
            ],
            $specs['operations']['deleteAll']['events']
        );
    }
    
    public function testGetClassLevelExcludedButOperationLevelIncludedServiceSpecification()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
        
        $this->assertArrayHasKey('operations', $specs);
        $this->assertInstanceOf('\ArrayObject', $specs['operations']);
        $this->assertInstanceOf('\ArrayObject', $specs['operations']['getInternal']);
        
        $this->assertEquals(
            ['context' => '*', 'events' => []],
            $specs['operations']['getInternal']->getArrayCopy()
        );
    }
    
    public function testGetClassLevelExcludedMethod()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
        
        $this->assertArrayHasKey('operations', $specs);
        
        $this->assertInstanceOf('\ArrayObject', $specs['operations']);
        $this->assertArrayNotHasKey('setInternal', $specs['operations']->getArrayCopy());
    }
    
    public function testGetOperationLevelExcludedMethod()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
    
        $this->assertInstanceOf('\ArrayObject', $specs['operations']);
        $this->assertArrayNotHasKey('doInternal', $specs['operations']->getArrayCopy());
    }
    
    public function testOperationInheritsFromParent()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
        
        $this->assertArrayHasKey('operations', $specs);
        
        $this->assertInstanceOf('\ArrayObject', $specs['operations']);
        $this->assertArrayHasKey('commonOperation', $specs['operations']->getArrayCopy());
    }
    
    public function testChildOperationOverwritesParentOperation()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
        $this->assertNotNull($specs['operations']['sharedOperation']['events']);
        
        $this->assertEquals(
            [
                ['type' => 'pre', 'name' => null, 'args' => null],
            ],
            $specs['operations']['sharedOperation']['events']
        );
    }
    
    public function testChildOperationInheritsParentOperation()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
        $this->assertNotNull($specs['operations']['templateOperation']['events']);
    
        $this->assertEquals(
            [
                ['type' => 'pre', 'name' => null, 'args' => null],
                ['type' => 'post', 'name' => null, 'args' => null],
            ],
            $specs['operations']['templateOperation']['events']
        );
    }
    
    public function testContextAwareOperation()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
        
        $this->assertArrayHasKey('operations', $specs);
        
        $this->assertEquals(
            'http-post',
            $specs['operations']['postOperation']['context']);
    }
    
    public function testMultiContextAwareOperation()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
    
        $this->assertEquals(
            ['http*', 'native'],
            $specs['operations']['httpOperation']['context']);
    }

    private function getArrayCopyDeep(ArrayObject $array)
    {
        $array = $array->getArrayCopy();
        
        foreach ($array as &$value) {
            if ($value instanceof ArrayObject) {
                $value = $this->getArrayCopyDeep($value);
            }
        }
        
        return $array;
    }
}

