<?php
namespace ValuSo\Command;

use ValuSo\Exception;
use Zend\EventManager\EventManager;
use Zend\EventManager\ResponseCollection;
use Zend\EventManager\EventInterface;
use Valu\Service\Exception;

/**
 * Command manager is responsible of maintaining list of subscribed
 * command listeners and dispatching commands to correct listeners
 * 
 * This class extends {@see \Zend\EventManager\EventManager} but does
 * not support all its features. This class is inteded to be used
 * only via {@see \ValuSo\Broker\ServiceBroker} and {@see \ValuSo\Broker\ServiceLoader} classes.
 * 
 * @author juhasuni
 */
class CommandManager extends EventManager
{
    /**
     * Triggers listeners for specific command
     * 
     * @see \Zend\EventManager\EventManager::triggerListeners()
     */
    protected function triggerListeners($command, CommandInterface $c, $callback = null)
    {
        $responses = new ResponseCollection();
        $listeners = $this->getListeners($command);
        
        $command->setResponses($responses);
        
        if ($listeners->isEmpty()) {
            return $responses;
        }
        
        $exception = null;
        
        foreach ($listeners as $listener) {
            
            try{
                $response  = call_user_func($listener->getCallback(), $command);
                $exception = null;
            } catch(Exception\SkippableException $ex) {
                
                if (!$exception) {
                    $exception = $ex;
                }
                
                if ($c->propagationIsStopped()) {
                    $responses->setStopped(true);
                    break;
                } else {
                    continue;
                }
            }
            
            // Trigger the listener's callback, and push its result onto the
            // response collection
            $responses->push($response);
            
            // If the event was asked to stop propagating, do so
            if ($c->propagationIsStopped()) {
                $responses->setStopped(true);
                break;
            }
            
            // If the result causes our validation callback to return true,
            // stop propagation
            if ($callback && call_user_func($callback, $responses->last())) {
                $responses->setStopped(true);
                break;
            }
        }
        
        if ($exception && $responses->isEmpty()) {
            throw $exception;
        }
        
        return $responses;
    }
}