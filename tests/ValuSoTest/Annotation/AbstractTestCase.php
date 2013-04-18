<?php
namespace ValuSoTest\Annotation;

use Zend\EventManager\EventManager;

use Zend\Stdlib\ArrayUtils;

use ValuSo\Broker\ServiceBroker;
use ValuSo\Command\Command;
use ValuSo\Annotation\AnnotationBuilder;
use ValuSo\Proxy\ServiceProxyGenerator;
use PHPUnit_Framework_TestCase;

/**
 * Abstract annotation test case.
 */
abstract class AbstractTestCase extends PHPUnit_Framework_TestCase
{
    const SERVICE_CLASS = 'ValuSoTest\TestAsset\SimpleService';
    
    /**
     *
     * @var AnnotationBuilder
     */
    protected $annotationBuilder;
    
    /**
     *
     * @var ServiceBroker
     */
    protected $serviceBroker;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        $this->serviceBroker = new ServiceBroker();
        $this->annotationBuilder = new AnnotationBuilder();
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->annotationBuilder = null;
        $this->serviceBroker = null;
        parent::tearDown();
    }

    /**
     * Generates a proxy service instance
     * 
     * @param array $configExtension Extension to loaded class metadata
     * @param string $class Service class name
     * @return \ValuSo\Feature\InvokableInterface Proxy service instance
     */
    protected function generateProxyService(array $configExtension = array(), $class = self::SERVICE_CLASS)
    {
        $annotationBuilder = new AnnotationBuilder();
        $config = $annotationBuilder->getServiceSpecification($class);
        $config['service_id'] = 'Test.Service';
        
        $config = ArrayUtils::merge($config->getArrayCopy(), $configExtension);
        $proxyGenerator = new ServiceProxyGenerator(null, null, uniqid());
        $proxyGenerator->generateProxyClass($class, $config);
        
        $service = new $class();
        $proxyService = $proxyGenerator->createProxyClassInstance($service);
        
        $eventManager = new EventManager();
        $proxyService->setEventManager($eventManager);
        
        return $proxyService;
    }
}