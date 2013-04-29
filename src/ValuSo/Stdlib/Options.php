<?php
namespace ValuSo\Stdlib;

use ArrayObject;
use Zend\Stdlib\ParameterObjectInterface;
use Zend\Stdlib\ParametersInterface;

class Options
    extends ArrayObject
    implements ParameterObjectInterface
{

	public function __get($key) {
	    
	    if (!$this->__isset($key)) {
	        return null;
	    }
	    
		return $this->offsetGet($key);
	}

	public function __isset($key) {
		return $this->offsetExists($key);
	}

	public function __set($key, $value) {
		$this->offsetSet($key, $value);
	}

	public function __unset($key) {
		$this->offsetUnset($key);
	}
}