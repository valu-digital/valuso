<?php
namespace ValuSo\Annotation;

use Zend\Filter\Boolean as BooleanFilter;

/**
 * Exclude annotation
 *
 * Presence of this annotation hints the service proxy 
 * to exclude this operation.
 *
 * @Annotation
 */
class Exclude
{
    /**
     * @var bool
     */
    protected $exclude = true;
    
    /**
     * Receive and process the contents of an annotation
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        if (!isset($data['value'])) {
            $data['value'] = true;
        }
    
        $exclude = $data['value'];
    
        if (!is_bool($exclude)) {
            $filter   = new BooleanFilter();
            $exclude = $filter->filter($exclude);
        }
    
        $this->exclude = $exclude;
    }
    
    /**
     * Get value of exclude flag
     *
     * @return bool
     */
    public function getExclude()
    {
        return $this->exclude;
    }
}
