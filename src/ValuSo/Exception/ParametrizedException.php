<?php
namespace ValuSo\Exception;

class ParametrizedException extends \Exception{
	
	private $rawMessage = '';
	
	private $vars = array();

	public function __construct($message, $vars = null, $code = 0, $previous = null){
		
		$this->rawMessage = $message;
		
		if(is_array($vars) && sizeof($vars)){
		    
		    $this->setVars($vars);
		    
			$keys = array_keys($vars);
			$keys = array_map(
				array($this, 'escapeVar'),
				$keys
			);
			
			$message = str_replace(
				$keys, 
				array_values($vars), 
				$message
			);
		}
		
		parent::__construct($message, $code, $previous);
	}
	
	public function getRawMessage()
	{
	    return $this->rawMessage;
	}
	
	public function setVars(array $vars){
		$this->vars = $vars;
	}
	
	public function getVars(){
		return $this->vars;
	}
	
	protected final function escapeVar($var){
		return '%' . $var . '%';
	}
}