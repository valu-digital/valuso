<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Annotation;
use ValuSo\Feature\ProxyAwareInterface;

/**
 * @Annotation\Version("0.9")
 * @Annotation\ExcludePattern("set")
 */
abstract class AbstractService extends AbstractExcludedService
{
    
    /**
     * @Annotation\Trigger("pre");
     * @Annotation\Trigger("post");
     */
    public function commonOperation()
    {
        return 'common';
    }
    
    /**
     * @Annotation\Trigger("pre");
     * @Annotation\Trigger("post");
     */
    public function sharedOperation()
    {
        return 'shared';
    }
    
    /**
     * @Annotation\Trigger("pre");
     * @Annotation\Trigger("post");
     */
    public function templateOperation()
    {
        return 'template';
    }
}