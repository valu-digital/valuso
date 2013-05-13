<?php
namespace ValuSoTest\TestAsset;

use ValuSoTest\TestAsset\Annotation as Annotation;

class CustomAnnotationService
{
    /**
     * @Annotation\Test("OK")
     */
    public function operation1($returnValue)
    {
        return $returnValue;
    }
}