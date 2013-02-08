<?php
namespace ValuSo\Broker;

use ValuSo\Command\CommandInterface;
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
     * Command instance
     *
     * @var CommandInterface
     */
    protected $command;

    /**
     * Exception
     *
     * @var \Exception
     */
    protected $exception;
    
    /**
     * Set command instance
     * 
     * @param CommandInterface $command
     */
    public function setCommand(CommandInterface $command)
    {
        $this->command = $command;
        
        // Both event and the command share same params
        $this->setParams($command->getParams());
    }
    
    /**
     * Retrieve command instance
     * 
     * @return \ValuSo\Command\CommandInterface
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Retrieve the name of the service
     * 
     * @return string
     */
    public function getService()
    {
        return $this->getCommand()->getService();
    }
    
    /**
     * Retrieve the name of the operation
     * 
     * @return string
     */
    public function getOperation()
    {
        return $this->getCommand()->getOperation();
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
        return $this->getCommand()->getContext();
    }

	/**
	 * Retrieve responses
	 * 
     * @return \Zend\EventManager\ResponseCollection
     */
    public function getResponses()
    {
        return $this->getCommand()->getResponses();
    }
}