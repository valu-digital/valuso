<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Annotation;
use ValuSo\Feature\ProxyAwareInterface;

/**
 * @Annotation\Exclude
 */
abstract class AbstractExcludedService
{
    public function excluded()
    {
        return true;
    }
}