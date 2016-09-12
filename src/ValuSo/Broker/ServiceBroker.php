<?php
namespace ValuSo\Broker;

use Zend\Di\ServiceLocatorInterface;
use Zend\EventManager\EventManagerAwareInterface;
use ValuSo\Command\Command;
use ValuSo\Command\CommandInterface;
use ValuSo\Broker\ServiceServiceLoader;
use ValuSo\Feature;
use	ValuSo\Exception\ServiceNotFoundException;
use	ValuSo\Queue\Job\ServiceJob;
use	Zend\EventManager\EventManagerInterface;
use	Zend\EventManager\ResponseCollection;
use Zend\EventManager\EventManager;
use ArrayObject;
use Traversable;
use ArrayAccess;
use ValuSo\Broker\Exception\ConfigurationException;

/**
 *
 * @author juhasuni
 *
 */
class ServiceBroker{

    const QUEUE_OPTION_NAME = 'queue_name';

	/**
	 * Service loader
	 *
	 * @var ServiceServiceLoader
	 */
	private $loader;

	/**
	 * Event manager
	 *
	 * @var EventManagerInterface
	 */
	private $eventManager;

	/**
	 * Default service context
	 *
	 * @var string
	 */
	private $defaultContext;

	/**
	 * Default identity
	 *
	 * @var \ArrayAccess
	 */
	private $defaultIdentity;

	/**
	 * Job queue manager
	 *
	 * @var \SlmQueue\Queue\QueuePluginManager
	 */
	private $queuePluginManager;

    /**
     * Default queue name
     *
     * @var string
     */
    private $defaultQueueName;

	/**
	 * Initialize and configure service broker
	 *
	 * @param array|Traversable $options {@see setOptions()}
	 */
	public function __construct($options = null){

	    $this->setDefaultContext(Command::CONTEXT_NATIVE);

		if(null !== $options){
		    $this->setOptions($options);
		}
	}

	/**
	 * Configure service broker
	 *
	 * Use this method to configure service broker.
	 * Currently supports only option 'loader' which
	 * calls {@see setLoader()}.
	 *
	 * @param array|Traversable $options
	 * @throws \InvalidArgumentException
	 * @return \ValuSo\Broker\ServiceBroker
	 */
	public function setOptions($options)
	{
	    if (!is_array($options) && !$options instanceof Traversable) {
	        throw new \InvalidArgumentException(sprintf(
                'Expected an array or Traversable; received "%s"',
                (is_object($options) ? get_class($options) : gettype($options))
	        ));
	    }

	    foreach ($options as $key => $value){

	        $key = strtolower($key);

	        if($key == 'loader'){
	            $this->setLoader($value);
	        }
	    }

	    return $this;
	}

	/**
	 * Retrieve default service context
	 *
	 * @return string
	 */
	public function getDefaultContext(){
	    return $this->defaultContext;
	}

	/**
	 * Set default service context
	 *
	 * @param string $context
	 */
	public function setDefaultContext($context){
	    $this->defaultContext = $context;
	    return $this;
	}

	/**
	 * Retrieve default identity
	 *
	 * @return \ArrayAccess|null
	 */
	public function getDefaultIdentity()
	{
	    if (!$this->defaultIdentity) {
	        $this->defaultIdentity = new ArrayObject(array());

	        if ($this->exists('Identity')) {
	            $responses = $this->execute(
                    'Identity',
                    'getIdentity',
                    array(),
                    function($response){if($response instanceof ArrayAccess) return true;});

	            if (sizeof($responses)) {
	                $this->defaultIdentity = $responses->last();
	            }

	            if ($this->defaultIdentity instanceof \ArrayObject) {
	                $this->defaultIdentity->setFlags(\ArrayObject::ARRAY_AS_PROPS);
	            } elseif(!$this->defaultIdentity) {
	                return new \ArrayObject([]);
	            }
	        }

	    }

	    return $this->defaultIdentity;
	}

	/**
	 * Set default identity
	 *
	 * @param ArrayAccess $identity
	 */
	public function setDefaultIdentity(ArrayAccess $identity)
	{
	    $this->defaultIdentity = $identity;
	    return $this;
	}

	/**
	 * Set service loader instance
	 *
	 * @param ServiceLoader $loader
	 */
	public function setLoader(ServiceLoader $loader){

		$this->loader = $loader;
		$that = $this;

		$this->loader->addInitializer(function ($instance) use ($that) {

		    // Inject broker to services
		    if ($instance instanceof Feature\ServiceBrokerAwareInterface) {
		        $instance->setServiceBroker($that);
		    }

		    // Inject event manager to services
		    if ($instance instanceof EventManagerAwareInterface) {
		        $instance->setEventManager($that->getEventManager());
		    }
		});

		return $this;
	}

	/**
	 * Fetch service loader
	 *
	 * If servive loader is not set, initializes new
	 * ServiceServiceLoader instance and returns it.
	 *
	 * @return \ValuSo\Broker\ServiceLoader
	 */
	public function getLoader()
	{
	    if (!$this->loader) {
	        $this->loader = new ServiceLoader();
	    }

		return $this->loader;
	}

	/**
	 * Get event manager
	 *
	 * Initializes empty event manager, if event manager
	 * instance is not previously set.
	 *
	 * @return EventManagerInterface
	 */
	public function getEventManager(){

		if(null === $this->eventManager){
			$this->eventManager = new EventManager();
		}

		return $this->eventManager;
	}

	/**
	 * Does a service exist?
	 *
	 * @param string $service
	 * @return boolean
	 */
	public function exists($service){
		return $this->getLoader()->exists($service);
	}

	/**
	 * Initialize and retrieve a new service Worker
	 *
	 * @param string $service
	 * @throws ServiceNotFoundException
	 * @return Worker
	 */
	public function service($service){

	    if(!$this->exists($service)){
	        throw new ServiceNotFoundException(sprintf('Service "%s" not found', $service));
	    }

		return new Worker($this, $service);
	}

	/**
	 * @see executeInContext()
	 */
	public function execute($service, $operation, $argv = array(), $callback = null)
	{
		return $this->executeInContext(
	        $this->getDefaultContext(),
	        $service,
	        $operation,
	        $argv,
	        $callback);
	}

	/**
	 * Execute service operation in context
	 *
	 * @param string $context
	 * @param string $service
	 * @param string $operation
	 * @param array $argv
	 * @param mixed $callback Valid PHP callback
	 * @param string $context
	 * @return ResponseCollection
	 */
	public function executeInContext($context, $service, $operation, $argv = array(), $callback = null)
	{
	    $command = new Command(
            $service,
            $operation,
            $argv,
	        $context);

	    return $this->dispatch($command, $callback);
	}

	/**
	 * Queue execution of service operation
	 *
	 * @param CommandInterface $command
	 * @param array $options
	 * @return ServiceJob
	 */
	public function queue(CommandInterface $command, array $options = [])
    {
        $queueName = null;

        if (isset($options[self::QUEUE_OPTION_NAME])) {
            $queueName = $options[self::QUEUE_OPTION_NAME];
            unset($options[self::QUEUE_OPTION_NAME]);
        }

	    $queue = $this->getQueue($queueName);

	    if ($command->getIdentity()) {
	        $identity = $command->getIdentity();
	    } else if ($this->getDefaultIdentity()) {
	        $identity = $this->getDefaultIdentity();
	    } else {
	        $identity = null;
	    }

	    if (method_exists($identity, 'toArray')) {
	        $identity = $identity->toArray();
	    } else if ($identity instanceof \ArrayObject) {
	        $identity = $identity->getArrayCopy();
	    }

	    $job = new ServiceJob();
	    $job->setup($command, $identity);
	    $job->setServiceBroker($this);

	    $queue->push($job, $options);
	    return $job;
	}

    /**
     * @param string|null $name Name of the queue or null to use default queue
     * @return object
     * @throws ConfigurationException
     */
	public function getQueue($name = null)
    {
        if (!$name && !$this->getDefaultQueueName()) {
            throw new ConfigurationException(
                'Default queue name is not configured');
        }

        $name = $name ?: $this->defaultQueueName;

        if (!$this->getQueuePluginManager()) {
            throw new ConfigurationException(
                'Queue plugin manager is not set');
        }

	    return $this->getQueuePluginManager()->get($name);
	}

    /**
     * @return \SlmQueue\Queue\QueuePluginManager
     */
    public function getQueuePluginManager()
    {
        return $this->queuePluginManager;
    }

    /**
     * @param \SlmQueue\Queue\QueuePluginManager $queuePluginManager
     */
    public function setQueuePluginManager(\Zend\ServiceManager\ServiceLocatorInterface $queuePluginManager)
    {
        $this->queuePluginManager = $queuePluginManager;
    }

    /**
     * @return mixed
     */
    public function getDefaultQueueName()
    {
        return $this->defaultQueueName;
    }

    /**
     * @param mixed $defaultQueueName
     */
    public function setDefaultQueueName($defaultQueueName)
    {
        $this->defaultQueueName = $defaultQueueName;
    }

	/**
	 * Execute operation
	 *
	 * @param CommandInterface $command
	 * @param mixed $callback
	 * @throws ServiceNotFoundException
	 * @throws \Exception
	 * @return ResponseCollection|null
	 */
	public function dispatch(CommandInterface $command, $callback = null){

	    if (!$command->getIdentity() && $this->getDefaultIdentity()) {
            $command->setIdentity($this->getDefaultIdentity());
	    }

	    $service     = $command->getService();
	    $operation   = $command->getOperation();
	    $context     = $command->getContext();
	    $argv        = $command->getParams();
	    $exception = null;

	    if(!$this->exists($command->getService())){
	        throw new ServiceNotFoundException(sprintf(
                'Service "%s" not found', $command->getService()));
	    }

	    $responses = null;

        // Notify, that a (background) job is about to start
        if ($command->getJob()) {
            $jobEvent = $this->createEvent(
                'job.start', $command);

            $this->getEventManager()
                ->trigger($jobEvent);
        }

		// Prepare and trigger init.<service>.<operation> event
		$initEvent = strtolower('init.'.$service.'.'.$operation);

		if(!$this->getEventManager()->getListeners($initEvent)->isEmpty()){
		    $e = $this->createEvent($initEvent, $command);

		    $eventResponses = $this->trigger(
	            $e,
	            function($response){if($response === false) return true;}
		    );

		    if($eventResponses->stopped() && $eventResponses->last() === false){
		        $responses = new ResponseCollection();
		        $responses->setStopped(true);
		    }
		}

		// Dispatch command
		if ($responses === null) {
		    try{
		        $responses = $this->getLoader()->getCommandManager()->trigger(
                    $command,
                    $callback
	            );
		    } catch(\Exception $ex) {
		        $exception = $ex;
		    }
		}

		// Prepare and trigger final.<service>.<operation> event
		$finalEvent = strtolower('final.'.$service.'.'.$operation);

		if(!$this->getEventManager()->getListeners($finalEvent)->isEmpty()){

		    $e = $this->createEvent($finalEvent, $command);

		    // Set exception
		    if ($exception) {
		        $e->setException($exception);
		    }

		    $this->trigger($e);

		    // Listeners have a chance to clear (or change) the exception
		    $exception = $e->getException();
		}

        // Notify that the job has ended
        if ($command->getJob()) {
            $jobEvent = $this->createEvent('job.end', $command);

            if ($exception) {
                $jobEvent->setException($exception);
            }
            $this->trigger($jobEvent);
        }

		// Throw exception if it still exists
		if ($exception instanceof \Exception) {
		    throw $exception;
		}

		return $responses;
	}

	/**
	 * Triggers an event
	 *
	 * @param ServiceEvent $event
	 * @param mixed $callback
	 */
	protected function trigger(ServiceEvent $event, $callback = null)
	{
		return $this->getEventManager()->trigger($event, $callback);
	}

	/**
	 * Create a new service event
	 *
	 * @param string $name
	 * @param CommandInterface $command
	 * @return \ValuSo\Broker\ServiceEvent
	 */
	protected function createEvent($name, CommandInterface $command)
	{
	    $event = new ServiceEvent();
	    $event->setName($name);
	    $event->setCommand($command);
	    $event->setParams($command->getParams());

	    return $event;
	}
}
