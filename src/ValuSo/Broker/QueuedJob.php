<?php
namespace ValuSo\Broker;

use SlmQueue\Job\AbstractJob;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use RuntimeException;
use ValuSo\Command\CommandInterface;
use ValuSo\Command\Command;

class QueuedJob 
    extends AbstractJob
    implements ServiceLocatorAwareInterface
{
    private $serviceLocator;
    
    private $serviceBroker;
    
    /**
     * @var CommandInterface
     */
    private $command;
    
    /**
     * Callback
     * 
     * @var mixed
     */
    private $callback;
    
    private $loaded = false;
    
    public function setup(CommandInterface $command, $identity, $callback = null)
    {
        if (!$identity) {
            throw new RuntimeException(
                'Unable to initialize Job: identity is not available');
        }
        
        $this->generateId($command, $identity);
        
        parent::setContent([
            'context'      => $command->getContext(),
            'service'      => $command->getName(),
            'operation'    => $command->getOperation(),
            'params'       => $command->getParams(),
            'callback'     => $callback,
            'identity'     => $identity
        ]);
    }
    
	public function execute()
    {
        $command    = $this->getCommand();
        $callback   = $this->getCallback();
        $broker     = $this->getServiceBroker();
        
        if (!$command->getIdentity()) {
            throw new RuntimeException(
                'Unable to execute Job: identity is not valid');
        }
        
        $broker->dispatch(
            $command,
            $callback);
    }
    
    public function getCommand()
    {
        if (!$this->loaded) {
            $this->load();
        }
        
        return $this->command;
    }
    
    public function getCallback()
    {
        if (!$this->loaded) {
            $this->load();
        }
        
        return $this->callback;
    }

    public function setServiceBroker(ServiceBroker $serviceBroker)
    {
        $this->serviceBroker = $serviceBroker;
    }
    
    public function getServiceBroker()
    {
        if (!$this->serviceBroker && $this->serviceLocator) {
            $this->setServiceBroker($this->serviceLocator->get('ServiceBroker'));
        }
        
        if (!$this->serviceBroker instanceof ServiceBroker) {
            throw new RuntimeException(
                'Unable to execute Job; Job is not correctly initialized (ServiceBroker is not available)');
        }
        
        return $this->serviceBroker;
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
    
    public function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }
    
    protected function load()
    {
        $this->loaded = true;
        $payload = $this->getContent();
        
        $command = new Command(
            $payload['service'], 
            $payload['operation'], 
            $payload['params'], 
            $payload['context']);
        
        $payloadIdentity = $payload['identity'];
        $identity = null;
        
        if (isset($payloadIdentity['username'])) {
            $identity = $this->resolveIdentity($payloadIdentity['username']);
        } else if($payloadIdentity) {
            $identity = $this->prepareIdentity($payloadIdentity);
        }
        
        if ($identity) {
            $command->setIdentity($identity);
        }
        
        $this->command = $command;
        $this->callback = isset($payload['callback']) ? $payload['callback'] : null;
        
        return true;
    }
    
    /**
     * Resolve identity based on username
     * 
     * @param string $username
     * @return boolean
     */
    protected function resolveIdentity($username)
    {
        $identitySeed = $this->getServiceBroker()
            ->service('User')
            ->resolveIdentity($username);
        
        if ($identitySeed) {
            return $this->prepareIdentity($identitySeed);
        } else {
            return false;
        }
    }
    
    /**
     * Build new identity from identity seed
     * 
     * @param array $identitySeed
     */
    protected function prepareIdentity($identitySeed)
    {
        return $this->getServiceBroker()
            ->service('Identity')
            ->setIdentity($identitySeed);
    }
    
    private final function generateId(CommandInterface $command, $identity)
    {
        $id = md5(
            $identity['username'] .'|'.
            $command->getService() .'|'.
            $command->getOperation() .'|'.
            microtime(true).'|'.
            rand(0, 100000));
        
        $this->setId($id);
    }
}