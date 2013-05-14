<?php
namespace ValuSo\Command;

use ValuSo\Exception;
use ValuSo\Broker\ServiceLoader;
use Zend\EventManager\ResponseCollection;
use Zend\Stdlib\PriorityQueue;
use \ArrayObject;

/**
 * Command manager
 * 
 * Command manager is responsible of maintaining list of subscribed
 * command listeners and dispatching commands to correct listeners
 * by calling their callbacks.
 * 
 * The behaviour of this class is similar to {@see \Zend\EventManager\EventManager}
 * but CommandManager does not support all of the EventManager's the features. 
 * 
 * This class is inteded to be used only via {@see \ValuSo\Broker\ServiceBroker} 
 * and {@see \ValuSo\Broker\ServiceLoader} classes.
 * 
 * @author juhasuni
 */
class CommandManager
{
    /**
     * Service loader
     * 
     * @var \ValuSo\Broker\ServiceLoader
     */
    protected $serviceLoader = null;
    
    /**
     * Subscribed service implementations
     * @var array Array of PriorityQueue objects
     */
    protected $services = array();
    
    /**
     * Retrieve service loader
     * 
     * @return \ValuSo\Broker\ServiceLoader
     */
    public function getServiceLoader()
    {
        if ($this->serviceLoader === null) {
            $this->serviceLoader = new ServiceLoader();
        }
        return $this->serviceLoader;
    }
    
    /**
     * Set service loader
     * 
     * @param ServiceLoader $serviceLoader
     */
    public function setServiceLoader(ServiceLoader $serviceLoader)
    {
        $this->serviceLoader = $serviceLoader;
    }
    
    /**
     * Attach service listener
     * 
     * @param string $service           Service name (case in-sensitive)
     * @param string|Closure $callback  Callback closure or service ID
     * @param int $priority             Listener priority (greatest priority is called first)
     * @return LazyCallbackHandler      Listener specs
     */
    public function attach($service, $callback, $priority = 1)
    {
        $service = $this->normalizeServiceName($service);
        
        // If we don't have a priority queue for the service yet, create one
        if (empty($this->services[$service])) {
            $this->services[$service] = new PriorityQueue();
        }
        
        if (!is_callable($callback)) {
            $id = $callback;
            $callback = null;
        } else {
            $id = null;
        }
        
        $listener = new LazyCallbackHandler(
            $callback, 
            array('service' => $service, 'priority' => $priority, 'service_id' => $id));
    
        // Inject the callback handler into the queue
        $this->services[$service]->insert($listener, $priority);
        return $listener;
    }
    
    /**
     * Detach service listener
     * 
     * @param LazyCallbackHandler $listener
     * @return boolean
     */
    public function detach(LazyCallbackHandler $listener)
    {
        $service = $listener->getMetadatum('service');
        $service = $this->normalizeServiceName($service);
        
        if (!$service || empty($this->services[$service])) {
            return false;
        }
        
        $return = $this->services[$service]->remove($listener);
        if (!$return) {
            return false;
        }
        
        if (!count($this->services[$service])) {
            unset($this->services[$service]);
        }
        
        return true;
    }
    
    /**
     * Test whether any listeners exist for given service
     * 
     * @param string $service
     * @return boolean
     */
    public function hasListeners($service)
    {
        return !empty($this->services[$service]);
    }
    
    /**
     * Trigger all listeners for a given command
     *
     * Can emulate triggerUntil() if the last argument provided is a callback.
     *
     * @param  CommandInterface $command
     * @param  null|callable $callback
     * @return ResponseCollection All listener return values
     * @throws Exception\InvalidCallbackException
     */
    public function trigger(CommandInterface $command, $callback = null)
    {
        if ($callback && !is_callable($callback)) {
            throw new Exception\InvalidCallbackException('Invalid callback provided');
        }
        
        $service = $this->normalizeServiceName($command->getService());
        $responses = new ResponseCollection();
        $command->setResponses($responses);
        
        if (!isset($this->services[$service]) || $this->services[$service]->isEmpty()) {
            return $responses;
        }
        
        $exception = null;
        
        foreach ($this->services[$service] as $listener) {
            
            // Lazy load service as needed
            if (!$listener->getCallback()) {
                $listener->setCallback(
                    $this->getServiceLoader()->load($listener->getMetadatum('service_id')));
            }
            
            try{
                $response  = call_user_func($listener->getCallback(), $command);
                $exception = null;
            } catch(Exception\SkippableException $ex) {
                
                if (!$exception) {
                    $exception = $ex;
                }
                
                if ($command->propagationIsStopped()) {
                    $responses->setStopped(true);
                    break;
                } else {
                    continue;
                }
            }
            
            // Push response into collection
            $responses->push($response);
            
            // If the event was asked to stop propagating, do so
            if ($command->propagationIsStopped()) {
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
    
    /**
     * Retrieve service name in normal form
     *
     * @param string $name
     * @return string
     */
    public final function normalizeServiceName($name){
        return strtolower($name);
    }
}