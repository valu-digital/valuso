<?php
namespace ValuSo\Broker;

use Zend\EventManager\ResponseCollection;
use Zend\EventManager\EventInterface;
use Zend\Stdlib\CallbackHandler;
use Valu\Service\Exception;

class CommandManager
{

    /**
     * Subscribed commands and their listeners
     * 
     * @var array Array of PriorityQueue objects
     */
    protected $commands = array();
    
    public function attach($command, $callback, $priority = 1)
    {
        // If we don't have a priority queue for the event yet, create one
        if (empty($this->commands[$command])) {
            $this->commands[$command] = new PriorityQueue();
        }
    
        // Create a callback handler, setting the event and priority in its metadata
        $listener = new CallbackHandler($callback, array('event' => $event, 'priority' => $priority));
    
        // Inject the callback handler into the queue
        $this->commands[$command]->insert($listener, $priority);
        return $listener;
    }
    
    /**
     * Unsubscribe a listener from a command
     *
     * @param  CallbackHandler $listener
     * @return bool Returns true if command and listener found, and unsubscribed; returns false if either command or listener not found
     * @throws Exception\InvalidArgumentException if invalid listener provided
     */
    public function detach($listener)
    {
        if (!$listener instanceof CallbackHandler) {
            throw new \InvalidArgumentException(sprintf(
                '%s: expected a ListenerAggregateInterface or CallbackHandler; received "%s"',
                __METHOD__,
                (is_object($listener) ? get_class($listener) : gettype($listener))
            ));
        }

        $command = $listener->getMetadatum('command');
        
        if (!$command || empty($this->commands[$command])) {
            return false;
        }
        
        $return = $this->commands[$command]->remove($listener);
        
        if (!$return) {
            return false;
        }
        
        if (!count($this->commands[$command])) {
            unset($this->commands[$command]);
        }
        return true;
    }
    
    /**
     * (non-PHPdoc)
     * 
     * @see \Zend\EventManager\EventManager::triggerListeners()
     */
    protected function triggerListeners($event, EventInterface $e, $callback = null)
    {
        $responses = new ResponseCollection();
        $listeners = $this->getListeners($event);
        
        if ($listeners->isEmpty()) {
            return $responses;
        }
        
        $exception = null;
        
        foreach ($listeners as $listener) {
            
            try{
                $response  = call_user_func($listener->getCallback(), $e);
                $exception = null;
            } catch(Exception\SkippableException $ex) {
                
                if (!$exception) {
                    $exception = $ex;
                }
                
                if ($e->propagationIsStopped()) {
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
            if ($e->propagationIsStopped()) {
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