<?php
namespace ValuSo\Annotation;

/**
 * Name annotation
 *
 * Use this annotation to specify service version.
 *
 * @Annotation
 */
class Version extends AbstractStringAnnotation
{
    /**
     * Retrieve the version
     *
     * @return null|string
     */
    public function getVersion()
    {
        return $this->value;
    }
}
