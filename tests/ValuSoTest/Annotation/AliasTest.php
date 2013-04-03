<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\Alias;
use ValuSoTest\TestAsset\TestService;

/**
 * Alias test case.
 */
class AliasTest extends AbstractTestCase
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
    
    public function testConstructAndGet()
    {
        $alias = new Alias(['value' => 'operationAlias']);
        $this->assertEquals('operationAlias', $alias->getAlias());
    }
    
    public function testOperationContainsAliasConfiguration()
    {
        $service = new TestService();
        $specs = $this->annotationBuilder->getServiceSpecification($service);
    
        $this->assertArrayHasKey('operations', $specs);
        $this->assertEquals(['save'], $specs['operations']['update']['aliases']);
    }
    
    public function testInvokeUsingAlias()
    {
        // save is an alias for update
        $this->assertTrue(
            $this->serviceBroker->service('Test.Service')->save('someitemid'));
    }
}