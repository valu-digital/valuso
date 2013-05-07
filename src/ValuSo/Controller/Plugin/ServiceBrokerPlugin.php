<?php
namespace ValuSo\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Stdlib\Dispatchable;
use ValuSo\Broker\ServiceBroker;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ServiceBrokerPlugin extends AbstractPlugin
{
    
    /**
     * Service locator
     * 
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $locator;
    
    /**
     * Service broker instance
     * 
     * @var \ValuSo\Broker\ServiceBroker
     */
    protected $serviceBroker;
    
    public function __construct(Broker $broker = null)
    {
        if($broker !== null){
        	$this->setBroker($broker);
        }
    }
    
    /**
     * Access service via service broker
     * 
     * @param string $name
     * @return \ValuSo\Broker\Worker
     */
    public function service($name){
        return $this->getServiceBroker()->service($name);
    }
    
    /**
     * Get service broker instance
     * 
     * @return \ValuSo\Broker\ServiceBroker
     */
    public function getServiceBroker()
    {
        if(!$this->serviceBroker){
            $locator = $this->getLocator();
            $this->setServiceBroker($locator->get('ServiceBroker'));
        }
        
        return $this->serviceBroker;
    }
    
    /**
     * Set service broker instance
     * 
     * @param Broker $broker
     */
    public function setServiceBroker(ServiceBroker $serviceBroker)
    {
        $this->serviceBroker = $serviceBroker;
    }
    
    /**
     * Get the locator
     *
     * @return Locator
     * @throws Exception\DomainException if unable to find locator
     */
    protected function getLocator()
    {
    	if ($this->locator) {
    		return $this->locator;
    	}
    	
    	$controller = $this->getController();
    
    	if (!$controller instanceof ServiceLocatorAwareInterface) {
    		throw new \Exception('ServiceBroker plugin requires controller implements ServiceLocatorAwareInterface');
    	}
    	
    	$locator = $controller->getServiceLocator();
    	
    	if (!$locator instanceof ServiceLocatorInterface) {
    		throw new \Exception('ServiceBroker plugin requires controller implements ServiceLocatorInterface');
    	}
    	
    	$this->locator = $locator;
    	return $this->locator;
    }
}