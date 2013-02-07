<?php
namespace ValuSo\Command;

use Zend\EventManager\ResponseCollection;
use Zend\EventManager\EventInterface;

/**
 * Service command interface
 */
interface CommandInterface extends EventInterface
{
    /**
     * Retrieve the name of the service
     * 
     * @return string
     */
    public function getService();
    
    /**
     * Set the name of the service
     * 
     * @param string $service
     * @return CommandInterface
     */
    public function setService($service);
    
    /**
     * Retrieve the name of the operation
     * 
     * @return string
     */
    public function getOperation();
    
    /**
     * Set the name of the operation
     * 
     * @param string $operation
     * @return CommandInterface
     */
    public function setOperation($operation);
    
    /**
     * Retrieve context
     * 
     * @return string
     */
    public function getContext();
    
    /**
     * Set context
     * 
     * @param string $context
     * @return CommandInterface
     */
    public function setContext($context);
    
    /**
     * Retrieve responses
     * 
     * @return \Zend\EventManager\ResponseCollection
     */
    public function getResponses();
    
    /**
     * Set responses
     * 
     * @param ResponseCollection $responses
     * @return CommandInterface
     */
    public function setResponses(ResponseCollection $responses);
}