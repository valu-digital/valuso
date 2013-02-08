<?php
namespace ValuSo\Command;

use Zend\EventManager\ResponseCollection;
use Zend\EventManager\Event;
use \ArrayObject;

/**
 * This class encapsulates service command information and event
 * processing information
 * 
 * @author juhasuni
 */
class Command 
    extends Event 
    implements CommandInterface
{
    
    /**
     * Command to be invoked internally
     * 
     * @var string
     */
    const CONTEXT_NATIVE = 'native';
    
    /**
     * Command to be invoked via HTTP interface
     * 
     * @var string
     */
    const CONTEXT_HTTP = 'http';
    
    /**
     * Command to be invoked via CLI
     * 
     * @var string
     */
    const CONTEXT_CLI = 'cli';

    /**
     * Operation name
     *
     * @var string
     */
    protected $operation;
    
    /**
     * Service context
     * 
     * @var string
     */
    protected $context;

    /**
     * Exception
     *
     * @var \Exception
     */
    protected $exception;
    
    /**
     * Response collection
     * @var ResponseCollection
     */
    protected $responses;
    
    /**
     * Initialize a new command
     *
     * @param string $service
     * @param string $operation
     * @param mixed $params
     * @param string $context
     * @return \ValuSo\Command\CommandInterface
     */
    public function __construct($service = null, $operation = null, $params = null, $context = null)
    {
        if ($service !== null) {
            $this->setService($service);
        }
        
        if ($operation !== null) {
            $this->setOperation($operation);
        }
        
        if ($params !== null) {
            $this->setParams($params);
        }
        
        if ($context !== null) {
            $this->setContext($context);
        }
    }
    
    /**
     * @see \Zend\EventManager\Event::setParams()
     */
    public function setParams($params) {
        if (is_array($params)) {
            $params = new ArrayObject($params);
        }
        
        return parent::setParams($params);
    }
    
    /**
     * @see \Zend\EventManager\Event::getParams()
     */
    public function getParams()
    {
        $params = parent::getParams();
        
        if (is_array($params)) {
            $params = new ArrayObject($params);
        }
        
        return $params;
    }

    /**
     * @see CommandInterface::getService()
     */
    public function getService()
    {
        return $this->getName();
    }

    /**
     * @see CommandInterface::setService()
     */
    public function setService($service)
    {
        $this->setName($service);
        return $this;
    }
    
    /**
     * @see CommandInterface::getOperation()
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * @see CommandInterface::setOperation()
     */
    public function setOperation($operation)
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * @see CommandInterface::getContext()
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @see CommandInterface::setContext()
     */
    public function setContext($context)
    {
        $this->context = $context;
        return $this;
    }
    
	/**
     * @see CommandInterface::getResponses()
     */
    public function getResponses()
    {
        return $this->responses;
    }

	/**
     * @see CommandInterface::setResponses()
     */
    public function setResponses(ResponseCollection $responses)
    {
        $this->responses = $responses;
        return $this;
    }
}