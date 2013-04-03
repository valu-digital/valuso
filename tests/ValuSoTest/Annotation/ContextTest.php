<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\Context;
use ValuSoTest\TestAsset\TestService;

/**
 * Context test case.
 */
class ContextTest extends AbstractTestCase
{
    
    /**
     * Alias
     * @var \ValuSo\Annotation\Alias
     */
    private $alias;
    
    public function setUp()
    {
        parent::setUp();
        
        // Register test service
        $this->serviceBroker->getLoader()->registerService(
            'test', 'Test.Service', 'ValuSoTest\TestAsset\TestService');
    }
    
    public function testConstructUsingStringAndGet()
    {
        $contextAnnotation = new Context(['value' => 'http']);
        $this->assertEquals('http', $contextAnnotation->getContext());
    }
    
    public function testConstructUsingArrayAndGet()
    {
        $contextAnnotation = new Context(['value' => ['http', 'native']]);
        $this->assertEquals(['http', 'native'], $contextAnnotation->getContext());
    }
    
    public function testOperationContainsAliasConfiguration()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
        $this->assertEquals(['save'], $specs['operations']['update']['aliases']);
    }
    
    public function testInvokeWithDefaultContext()
    {
        $this->assertTrue(
            $this->serviceBroker->service('Test.Service')->update('someitemid'));
    }
    
    public function testInvokeWithCorrectContext()
    {
        $this->assertEquals(
            'posted',
            $this->serviceBroker->service('Test.Service')->context('http-post')->postOperation('someitemid'));
    }
    
    public function testInvokeWithMultipleSupportedContexts()
    {
        $this->assertEquals(
            'done',
            $this->serviceBroker->service('Test.Service')->context('http-post')->httpOperation('someitemid'));
    }
    
    /**
     * @expectedException \ValuSo\Exception\UnsupportedContextException
     */
    public function testInvokeWithInCorrectContext()
    {
        $this->assertEquals(
            'posted',
            $this->serviceBroker->service('Test.Service')->context('native')->postOperation('someitemid'));
    }
}