<?php
namespace ValuSo\Broker;

use ArrayAccess;
use ValuSo\Command\Command;
use ValuSo\Broker\ServiceBroker;

/**
 * Worker separates certain Unit Of Work from others
 * when executing a service operation
 * 
 * @author Juha Suni
 */
class Worker{
	
	/**
	 * Service broker
	 * 
	 * @var \ValuSo\Broker\ServiceBroker
	 */
	protected $broker;
	
	/**
	 * Service name
	 * 
	 * @var string
	 */
	protected $service;
	
	/**
	 * Callback 
	 * 
	 * @var callback
	 */
	protected $callback;
	
	/**
	 * Registered service arguments
	 * 
	 * @var array|null
	 */
	protected $args = null;
	
	/**
	 * Service context
	 * 
	 * @var string
	 */
	protected $context = null;
	
	/**
	 * Identity
	 * 
	 * @var \ArrayAccess
	 */
	protected $identity = null;
	
	/**
	 * Queue priority
	 * 
	 * @var int|null
	 */
	protected $priority = null;
	
	public function __construct(ServiceBroker $broker, $service)
	{
		$this->broker 	= $broker;
		$this->service 	= $service;
	}
	
	/**
	 * Set service context
	 * 
	 * @param string $context
	 */
	public function context($context)
	{
	    $this->context = $context;
	    return $this;
	}
	
	/**
	 * Set user identity information for this operation
	 * 
	 * @param ArrayAccess $identity
	 * @return \ValuSo\Broker\Worker
	 */
	public function identity(ArrayAccess $identity)
	{
	    $this->identity = $identity;
	    return $this;
	}
	
	/**
	 * Set a callback function to execute on
	 * each service implementation in service stack
	 * 
	 * When callback function returns true, the service
	 * event is stopped and the next service in stack won't be 
	 * processed.
	 * 
	 * @param callback $callback Valid callback function
	 * @return \ValuSo\Broker\Worker
	 */
	public function until($callback)
	{
		$this->callback = $callback;
		return $this;
	}
	
	/**
	 * @return \ValuSo\Broker\Worker
	 */
	public function untilTrue()
	{
	    $this->until(function($response){if($response === true) return true;});
	    return $this;
	}
	
	/**
	 * @return \ValuSo\Broker\Worker
	 */
	public function untilFalse()
	{
	    $this->until(function($response){if($response === false) return true;});
	    return $this;
	}
	
	/**
	 * Set the args for the operation
	 * 
	 * @param array $args
	 * @return \ValuSo\Broker\Worker
	 */
	public function args($args)
	{
		$this->args = $args;
		return $this;
	}
	
	/**
	 * Set operation to be queued
	 *
	 * @param boolean $queued
	 * @return Worker
	 */
	public function queue($priority = 1)
	{
	    $this->priority = $priority;
	}
	
	/**
	 * Execute operation
	 * 
	 * @param string $operation   Operation
	 * @param array|null $args    Arguments to use as command parameters
	 * 
	 * @return \Zend\EventManager\ResponseCollection
	 */
	public function exec($operation, $args = null)
	{
		$args = ($args) ?:$this->args;
		$command = new Command($this->service, $operation, $args);
		
		if ($this->context) {
		    $command->setContext($this->context);
		} else {
		    $command->setContext($this->broker->getDefaultContext());
		}
		
		if ($this->identity) {
		    $command->setIdentity($this->identity);
		} else {
		    $command->setIdentity($this->broker->getDefaultIdentity());
		}
		
		if ($this->priority !== null) {
		    return $this->broker->queue($command, $this->callback);
		} else {
		    return $this->broker->dispatch($command, $this->callback);
		}
	}
	
	/**
	 * Convenience method for executing the first
	 * valid service operation and returning the first response
	 * 
	 * Calling this method is equal to following chain:
	 * <code>
	 * $worker ->until(function(){return true;})
	 *         ->exec($operation, $args)
	 *         ->first();
	 * </code>
	 *   
	 * @param string $operation
	 * @param array $args
	 * @return mixed
	 */
	public function __call($operation, $args)
	{
	    return 	$this->until(array($this, '_break'))
	            ->exec($operation, $args)
	    		->first();
	}
	
	public function _break()
	{
	    return true;
	}
}