<?php
namespace ValuSo\Broker;

use ValuSo\Invoker\InvokerInterface;
use ValuSo\Exception;
use ValuSo\Invoker\DefinitionBased;
use ValuSo\Feature;

/**
 * Provides invokable wrapper interface for any object acting as a service
 * 
 */
class ServiceWrapper
    implements Feature\InvokerAwareInterface
{

    /**
     * Service object
     * 
     * @var object
     */
    private $service;
    
    /**
     * Current command
     * 
     * @var CommandInterface
     */
    private $command;
    
    public function __construct($service)
    {
        $this->setService($service);
    }
    
    /**
     * Invokes service operation
     * 
     * @param CommandInterface $command
     */
    public function __invoke(CommandInterface $command)
    {
        $this->setCommand($command);
    }
    
    /**
     * Retrieve service
     * 
     * @return object
     */
    public function getService()
    {
        return $this->service;
    }
    
    /**
     * Retrieve current command
     * 
     * @return \ValuSo\Broker\CommandInterface
     */
    public function getCommand()
    {
        return $this->command;
    }
    
    /**
     * Retrieve invoker instance
     *
     * @return InvokerInterface
     * @valu\service\ignore
     */
    public function getInvoker(){
         
        if(!$this->invoker){
            $this->invoker = new DefinitionBased();
        }
         
        return $this->invoker;
    }
    
    /**
     * Set invoker
     *
     * @param InvokerInterface $invoker
     */
    public function setInvoker(InvokerInterface $invoker){
        $this->invoker = $invoker;
    }
    
    /**
     * Set service object
     * 
     * @param object $service
     * @throws Exception\InvalidArgumentException
     */
    private function setService($service)
    {
        if (!is_object($service)) {
            throw new Exception\InvalidArgumentException(
                sprintf('Invalid service; %s given, object expected', gettype($service)));
        }
        
        if ($service instanceof Feature\ServiceWrapperAwareInterface) {
            $service->setServiceWrapper($this);
        }
        
        $this->service = $service;
    }
    
    /**
     * Set command
     * 
     * @param CommandInterface $command
     */
    private function setCommand(CommandInterface $command)
    {
        $this->command = $command;
    }
}