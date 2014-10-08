<?php
namespace ValuSo\Json;

use Zend\Json\Encoder as BaseEncoder;
use Zend\Json\Exception\RecursionException;
use ValuSo\Json\Exception\ObjectEncodingException;

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
            $json = json_encode(
                $value,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
            
            if ($json === false) {
                throw new ObjectEncodingException(
                    sprintf('Error encoding object of type %s', get_class($value)));
            } else {
                return $json;
            }
        }
    }
}