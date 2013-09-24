<?php
namespace ValuSo\Service;

use ValuSo\Broker\ServiceBroker;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ValuSo\Annotation\AnnotationBuilder;
use ValuSo\Annotation as ValuService;

class MetaService
    implements ServiceLocatorAwareInterface
{
    
    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;
    
    /**
     * Annotation builder instance
     * 
     * @var AnnotationBuilder
     */
    protected $annotationBuilder;
    
    /**
     * Service broker instance
     *
     * @var \ValuSo\Broker\ServiceBroker
     */
    protected $serviceBroker;
    
    /**
     * Retrieve service description
     * 
     * @param string $serviceId Service ID
     * 
     * @ValuService\Context({"cli", "http-get"})
     */
    public function describe($serviceId)
    {
        $loader = $this->getServiceLoader();
        $options = $loader->getServiceOptions($serviceId);
        
        $service = $loader->getServicePluginManager()->get($serviceId, $options, true, false);
        
        if (!$service) {
            return null;
        }
        
        $description = [];
        $description['service_id'] = $serviceId;
        if (is_callable($service)) {
            return $description;
        }
        
        $specs = $this->getAnnotationBuilder()->getServiceSpecification($service);
        $specs = $specs->getArrayCopy();
        
        $description = array_merge($specs, $description);
        return $description;
    }
    
    /**
     * Retrieve description for all services matched by IDs in
     * array $services
     * 
     * @param array $services
     * @return array
     * 
     * @ValuService\Context({"cli", "http-get"})
     */
    public function describeMany(array $services)
    {
        $descriptions = [];
        foreach ($services as $serviceId) {
            $descriptions[] = $this->describe($serviceId);
        }
        
        return $descriptions;
    }
    
    /**
     * Retrieve description for all services
     * 
     * @return array
     * 
     * @ValuService\Context({"cli", "http-get"})
     */
    public function describeAll()
    {
        $services = $this->getServiceLoader()->getServiceIds();
        return $this->describeMany($services);
    }
    
	/** 
     * @see \Zend\ServiceManager\ServiceLocatorAwareInterface::getServiceLocator()
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;   
    }

	/**
     * @see \Zend\ServiceManager\ServiceLocatorAwareInterface::setServiceLocator()
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }
    
    /**
     * @return \ValuSo\Annotation\AnnotationBuilder
     */
    public function getAnnotationBuilder()
    {
        if (!$this->annotationBuilder) {
            $this->setAnnotationBuilder(
                $this->getServiceLocator()->get('valu_so.annotation_builder'));    
        }
        
        return $this->annotationBuilder;
    }
    
    /**
     * Retrieve service broker instance
     *
     * @return \ValuSo\Broker\ServiceBroker
     */
    public function getServiceBroker()
    {
        return $this->serviceBroker;
    }
    
    /**
     * @see \ValuSo\Feature\ServiceBrokerAwareInterface::setServiceBroker()
     */
    public function setServiceBroker(ServiceBroker $serviceBroker)
    {
        $this->serviceBroker = $serviceBroker;
    }
    
    /**
     * @param AnnotationBuilder $annotationBuilder
     */
    public function setAnnotationBuilder(AnnotationBuilder $annotationBuilder)
    {
        $this->annotationBuilder = $annotationBuilder;
    }
    
    /**
     * @return \ValuSo\Broker\ServiceLoader
     */
    protected function getServiceLoader()
    {
        return $this->getServiceBroker()->getLoader();
    }
}