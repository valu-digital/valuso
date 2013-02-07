<?php
namespace ValuSo\Broker;

use Zend\EventManager\ResponseCollection;
use Zend\EventManager\Event;
use \ArrayObject;

/**
 * This class encapsulates service command information and event
 * processing information
 * 
 * @author juhasuni
 */
class ServiceEvent 
    extends Event 
{

    /**
     * Service service
     *
     * @var string
     */
    protected $service;

    /**
     * Operation
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
     * Retrieve the name of the service
     * 
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Set the name of the service
     * 
     * @param string
     * @return ServiceEvent
     */
    public function setService($service)
    {
        $this->service = $service;
        return $this;
    }
    
    /**
     * Retrieve the name of the operation
     * 
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * Set the name of the operation
     * 
     * @param string
     * @return ServiceEvent      
     */
    public function setOperation($operation)
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * Retrieve current exception, if any
     * 
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Set current exception
     * 
     * @param \Exception $exception
     * @return ServiceEvent
     */
    public function setException(\Exception $exception)
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * Retrieve current service context
     * 
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set current service context
     * 
     * @param unknown_type $context
     * @return ServiceEvent
     */
    public function setContext($context)
    {
        $this->context = $context;
        return $this;
    }
    
	/**
	 * Retrieve responses
	 * 
     * @return \Zend\EventManager\ResponseCollection
     */
    public function getResponses()
    {
        return $this->responses;
    }

	/**
	 * Set responses
	 * 
     * @param \Zend\EventManager\ResponseCollection $responses
     * @return ServiceEvent
     */
    public function setResponses($responses)
    {
        $this->responses = $responses;
        return $this;
    }
    
    /**
	 * Create a new service event
	 *
	 * @param string $name
	 * @param string $context
	 * @param string $service
	 * @param string $operation
	 * @param array $argv
	 * @return ServiceEvent
	 */
	protected static function create($name, $context, $service, $operation, $argv)
	{
	    $argv = is_null($argv) ? new ArrayObject() : $argv;
	    
	    $event = new ServiceEvent();
	    $event->setName($name);
	    $event->setContext($context);
	    $event->setService($service);
	    $event->setOperation($operation);
	    $event->setParams($argv);
	
	    return $event;
	}
}