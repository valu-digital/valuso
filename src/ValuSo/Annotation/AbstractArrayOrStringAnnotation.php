<?php
namespace ValuSo\Annotation;

use ValuSo\Exception;

abstract class AbstractArrayOrStringAnnotation
{
    /**
     * @var array|string
     */
    protected $value;

    /**
     * Receive and process the contents of an annotation
     *
     * @param  array $data
     * @throws Exception\AnnotationException if a 'value' key is missing, or its value is not an array or string
     */
    public function __construct(array $data)
    {
        if (!isset($data['value']) || (!is_array($data['value']) && !is_string($data['value']))) {
            throw new Exception\AnnotationException(sprintf(
                '%s expects the annotation to define an array or string; received "%s"',
                get_class($this),
                isset($data['value']) ? gettype($data['value']) : 'null'
            ));
        }
        $this->value = $data['value'];
    }
}
