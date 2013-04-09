<?php
namespace ValuSoTest\Annotation;

use ValuSo\Command\Command;

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
    
    /**
     * Service proxy instance
     */
    private $serviceProxy;
    
    public function setUp()
    {
        parent::setUp();
        
        $this->serviceProxy = $this->generateProxyService([
            'operations' => [
                'operation1' => [
                    'contexts' => ['native']
                ],
                'operation2' => [
                    'contexts' => ['http-post']
                ],
                'operation3' => [
                    'contexts' => ['http-post', 'http-get']
                ]
            ]        
        ]);
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
    
    public function testOperationContainsContextConfiguration()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
        $this->assertEquals(["http*", "native"], $specs['operations']['httpOperation']['contexts']);
    }
    
    public function testInvokeWithDefaultContext()
    {
        $serviceProxy = $this->generateProxyService();
        $command = new Command();
        $command->setOperation('operation1');
        $command->setParam('returnValue', true);
        $command->setContext(Command::CONTEXT_NATIVE);
        
        $this->assertTrue(
            $serviceProxy->__invoke($command));
    }
    
    public function testInvokeWithCorrectContext()
    {
        $command = new Command();
        $command->setOperation('operation2');
        $command->setParam('returnValue', true);
        $command->setContext('http-post');
        
        $this->assertTrue(
            $this->serviceProxy->__invoke($command));
    }
    
    public function testInvokeWithMultipleSupportedContexts()
    {
        $command = new Command();
        $command->setOperation('operation3');
        $command->setParam('returnValue', true);
        $command->setContext('http-get');
        
        $this->assertTrue(
            $this->serviceProxy->__invoke($command));
    }
    
    /**
     * @expectedException \ValuSo\Exception\UnsupportedContextException
     */
    public function testInvokeWithInCorrectContext()
    {
        $command = new Command();
        $command->setOperation('operation3');
        $command->setParam('returnValue', true);
        $command->setContext('native');
        
        $this->serviceProxy->__invoke($command);
    }
}