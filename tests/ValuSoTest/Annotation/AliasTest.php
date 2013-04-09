<?php
namespace ValuSoTest\Annotation;

use ValuSo\Command\Command;

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
        // make save as an alias for update
        $config = [
            'operations' => [
                'operation1' => [
                    'aliases' => ['operationAlias']
                ]
            ]
        ];
        
        $serviceProxy = $this->generateProxyService($config);
        $command = new Command();
        $command->setOperation('operationAlias');
        $command->setParam('returnValue', true);
        
        $this->assertTrue(
            $serviceProxy->__invoke($command));
    }
}