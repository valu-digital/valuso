<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\Exclude;

/**
 * Alias test case.
 */
class ExcludeTest extends AbstractTestCase
{

    public function testConstructWithBooleanTrueAndGet()
    {
        $alias = new Exclude(['value' => true]);
        $this->assertTrue($alias->getExclude());
    }
    
    public function testConstructWithBooleanFalseAndGet()
    {
        $alias = new Exclude(['value' => false]);
        $this->assertFalse($alias->getExclude());
    }
}