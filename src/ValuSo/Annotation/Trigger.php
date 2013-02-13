<?php
namespace ValuSo\Annotation;

/**
 * Event trigger annotation
 *
 * Presence of this annotation hints to the service proxy 
 * to trigger an event on this operation.
 *
 * @Annotation
 */
class Trigger extends AbstractArrayOrStringAnnotation
{
    /**
     * Retrieve the event specs
     *
     * @return null|string
     */
    public function getTrigger()
    {
        return $this->value;
    }
}
