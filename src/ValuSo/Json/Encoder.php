<?php
namespace ValuSo\Json;

use Zend\Json\Json;
use Zend\Json\Encoder as BaseEncoder;
use Zend\Json\Exception\RecursionException;

class Encoder extends BaseEncoder
{
    protected function _encodeObject(&$value)
    {
        if ($this->cycleCheck) {
            if ($this->_wasVisited($value)) {
        
                if (isset($this->options['silenceCyclicalExceptions'])
                        && $this->options['silenceCyclicalExceptions']===true) {
        
                    return '"* RECURSION (' . str_replace('\\', '\\\\', get_class($value)) . ') *"';
        
                } else {
                    throw new RecursionException(
                            'Cycles not supported in JSON encoding, cycle introduced by '
                            . 'class "' . get_class($value) . '"'
                    );
                }
            }
        
            $this->visited[] = $value;
        }
        
        if (method_exists($value, 'toJson')) {
            return $value->toJson();
        } elseif (method_exists($value, 'toArray')) {
            $array = $value->toArray();
            return $this->_encodeArray($array);
        } else {
            return Json::encode($value, true);
        }
    }
}