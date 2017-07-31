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
        $payload = $this->getContent();
        
        $command = new Command(
            $payload['service'], 
            $payload['operation'], 
            $payload['params'], 
            $payload['context']);
        
        $payloadIdentity = $payload['identity'];
        $identity = null;
        
        if (isset($payloadIdentity['username'])) {
            $identity = $this->resolveIdentity(
                $payloadIdentity['username'], 
                $payloadIdentity['account']);
        } else if($payloadIdentity) {
            $identity = $this->prepareIdentity($payloadIdentity);
        }
        
        if ($identity) {
            $command->setIdentity($identity);
        }
        
        $command->setJob($this);
        
        return $command;
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
     * Resolve identity based on username
     * 
     * @param string $username
     * @param string $accountId
     * @return boolean
     */
    protected function resolveIdentity($username, $accountId)
    {
        $identitySeed = $this->getServiceBroker()
            ->service('User')
            ->resolveIdentity($username);
        
        $accountIds = isset($identitySeed['accountIds']) ? $identitySeed['accountIds'] : [];
    
        if (in_array($accountId, $accountIds)) {
            $identitySeed['account']    = $accountId;
            $identitySeed['accountId']  = $accountId;
        }
        
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
        $this->getServiceBroker()
            ->service('Identity')
            ->setIdentity($identitySeed);
        
        $identity = $this->getServiceBroker()
            ->service('Identity')
            ->getIdentity();
        
        $this->getServiceBroker()
             ->setDefaultIdentity($identity);
        
        return $identity;
    }
}