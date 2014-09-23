<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Annotation;

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