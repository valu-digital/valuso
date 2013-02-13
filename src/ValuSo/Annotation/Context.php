<?php
namespace ValuSo\Annotation;

use ValuSo\Exception;

/**
 * Context annotation
 *
 * Presence of this annotation hints to the service proxy 
 * to execute the operation in this context only.
 *
 * @Annotation
 */
class Context extends AbstractArrayOrStringAnnotation
{
    /**
     * Get context
     *
     * @return string
     */
    public function getContext()
    {
        return $this->value;
    }
}