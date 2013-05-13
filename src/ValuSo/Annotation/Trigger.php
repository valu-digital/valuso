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
    
    /**
     * Retrieve event description
     * 
     * Event description is an array that contains following keys:
     * - type (event type, which is either 'pre' or 'post')
     * - name (name of the event)
     * - args (event arguments)
     * 
     * @return array
     */
    public function getEventDescription()
    {
        $event = array(
            'type' => null,
            'name' => null,
            'args' => null,
            'params' => null
        );
        
        if (is_string($this->value)) {
            $event['type'] = $this->value;
        } else {
            $event['type'] = isset($this->value['type']) ? $this->value['type'] : null;
            $event['name'] = isset($this->value['name']) ? $this->value['name'] : null;
            $event['args'] = isset($this->value['args']) ? $this->value['args'] : null;
            $event['params'] = isset($this->value['params']) ? $this->value['params'] : null;
        }
        
        return $event;
    }
}
