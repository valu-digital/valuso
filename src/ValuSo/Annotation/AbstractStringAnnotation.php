<?php
namespace ValuSo\Annotation;

use ValuSo\Exception;

abstract class AbstractStringAnnotation
{
    /**
     * @var string
     */
    protected $value;

    /**
     * Receive and process the contents of an annotation
     *
     * @param  array $data
     * @throws Exception\AnnotationException if a 'value' key is missing, or its value is not a string
     */
    public function __construct(array $data)
    {
        if (!isset($data['value']) || !is_string($data['value'])) {
            throw new Exception\AnnotationException(sprintf(
                '%s expects the annotation to define a string; received "%s"',
                get_class($this),
                gettype($data['value'])
            ));
        }
        $this->value = $data['value'];
    }
}
