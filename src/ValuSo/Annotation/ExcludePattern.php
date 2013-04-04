<?php
namespace ValuSo\Annotation;

use ValuSo\Exception;
use Zend\Stdlib\ErrorHandler;

/**
 * Exclude annotation
 *
 * Presence of this annotation hints to the service proxy 
 * to exclude all methods matching the pattern.
 *
 * @Annotation
 */
class ExcludePattern extends AbstractArrayOrStringAnnotation
{
    
    const PATTERN_GET = 'get';
    
    const PATTERN_SET = 'set';
    
    const PATTERN_GETSET = 'getset';
    
    protected $patterns = array(
        'get' => '/^get[A-Z]/', 
        'set' => '/^set[A-Z]/', 
        'getset' => '/^(g|s)et[A-Z]/', 
    );
    
    /**
     * Receive and process the contents of an annotation
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        
        if (is_array($this->value)) {
            foreach ($this->value as $key => &$value) {
                $this->filter($value);
            }
        } else {
            $this->filter($this->value);
        }
    }
    
    /**
     * Get exclude pattern
     *
     * @return string
     */
    public function getExcludePattern()
    {
        return $this->value;
    }
    
    /**
     * Filter value
     * 
     * @param string $value Regexp or name of pattern
     * @throws Exception\InvalidArgumentException
     */
    private function filter(&$value)
    {
        if (isset($this->patterns[$value])) {
            $value = $this->patterns[$value];
        } else {
            // Test that pattern is a valid REGEXP pattern
            ErrorHandler::start();
            $this->pattern = (string) $value;
            $status        = preg_match($this->pattern, "Test");
            $error         = ErrorHandler::stop();
        
            if (false === $status) {
                throw new Exception\InvalidArgumentException("Internal error parsing the pattern '{$this->value}'", 0, $error);
            }
        }
    }
}