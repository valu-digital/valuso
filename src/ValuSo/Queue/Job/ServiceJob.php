<?php
namespace ValuSo\Queue\Job;

use SlmQueue\Job\AbstractJob;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ValuSo\Command\CommandInterface;
use ValuSo\Command\Command;
use ValuSo\Broker\ServiceBroker;
use RuntimeException;
use ArrayAccess;

class ServiceJob 
    extends AbstractJob
    implements ServiceLocatorAwareInterface
{
    private $serviceLocator;
    
    private $serviceBroker;
    
    /**
     * @var CommandInterface
     */
    private $command;
    
    private $loaded = false;
    
    /**
     * Setup ServiceJob
     * 
     * @param CommandInterface $command
     * @param ArrayAccess $identity
     * @throws RuntimeException
     */
    public function setup(CommandInterface $command, $identity)
    {
        if (!$identity) {
            throw new RuntimeException(
                'Unable to initialize Job: identity is not available');
        }
        
        $this->setContent([
            'context'      => $command->getContext(),
            'service'      => $command->getName(),
            'operation'    => $command->getOperation(),
            'params'       => $command->getParams()->getArrayCopy(),
            'identity'     => $identity
        ]);
    }
    
    /**
     * Execute job
     * 
     * @throws RuntimeException
     */
	public function execute()
    {
        $command    = $this->getCommand();
        $broker     = $this->getServiceBroker();
        
        if (!$command->getIdentity()) {
            throw new RuntimeException(
                'Unable to execute Job: identity is not available');
        }
        
        $broker->dispatch($command);
    }
    
    /**
     * Retrieve command
     * 
     * @return CommandInterface
     */
    public function getCommand()
    {
        if (!$this->loaded) {
            $this->load();
        }
        
        return $this->command;
    }

    /**
     * Set service broker instance
     * 
     * @param ServiceBroker $serviceBroker
     */
    public function setServiceBroker(ServiceBroker $serviceBroker)
    {
        $this->serviceBroker = $serviceBroker;
    }
    
    /**
     * Retrieve service broker instance
     * 
     * @throws RuntimeException
     * @return ServiceBroker
     */
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

    /**
     * Retrieve service locator instance
     * 
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
    
    /**
     * Set service locator instance
     * 
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }
    
    /**
     * Load command and identity details from job payload
     * 
     * When job is serialized, command and identity parameters
     * are stored in an array. This method reads the contents
     * of the array to re-populate command and identity, so
     * that the command may be executed. 
     * 
     * @return boolean
     */
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
}