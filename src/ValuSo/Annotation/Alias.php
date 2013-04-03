<?php
namespace ValuSo\Annotation;

/**
 * Alias annotation
 *
 * Use this annotation to specify an alias for operation.
 *
 * @Annotation
 */
class Alias extends AbstractStringAnnotation
{
    /**
     * Retrieve the alias
     *
     * @return null|string
     */
    public function getAlias()
    {
        return $this->value;
    }
}
