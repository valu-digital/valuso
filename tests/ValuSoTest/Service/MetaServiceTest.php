<?php
namespace ValuSoTest\Service;

use Zend\ServiceManager\ServiceManager;
use Zend\EventManager\EventManager;
use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Service\MetaService;
use ValuSo\Broker\ServiceBroker;
use ValuSo\Annotation\AnnotationBuilder;

class MetaServiceTest extends TestCase
{
    const ANNOTATED_SERVICE_CLASS = 'ValuSoTest\TestAsset\AnnotatedService';
    
    /**
     * @var MetaService
     */
    private $metaService;
    
    public function setUp()
    {
        $this->metaService = new MetaService();
        
        $broker = new ServiceBroker();
        $annotationBuilder = new AnnotationBuilder();
        
        $this->metaService->setServiceBroker($broker);
        $this->metaService->setAnnotationBuilder($annotationBuilder);
    }
    
    public function testDescribe()
    {
        $this->metaService->getServiceBroker()->getLoader()->registerServices([
            'TestService' => [
                'name' => 'Test.Service',
                'class' => self::ANNOTATED_SERVICE_CLASS
            ]
        ]);

        $specs = $this->metaService->describe('TestService');

        $this->assertArrayHasKey('operations', $specs);
        $this->assertArrayHasKey('contexts', $specs);
    }
    
    public function testDescribeMany()
    {
        $this->metaService->getServiceBroker()->getLoader()->registerServices([
            'TestService1' => [
                'name' => 'Test.Service1',
                'class' => self::ANNOTATED_SERVICE_CLASS
            ],
            'TestService2' => [
                'name' => 'Test.Service2',
                'class' => self::ANNOTATED_SERVICE_CLASS
            ]
        ]);

        $specs = $this->metaService->describeMany(['TestService1', 'TestService2']);
        $this->assertEquals(2, sizeof($specs));
    }
    
    public function testDescribeAll()
    {
        $this->metaService->getServiceBroker()->getLoader()->registerServices([
            'TestService1' => [
                'name' => 'Test.Service1',
                'class' => self::ANNOTATED_SERVICE_CLASS
            ],
            'TestService2' => [
                'name' => 'Test.Service2',
                'class' => self::ANNOTATED_SERVICE_CLASS
            ]
        ]);

        $specs = $this->metaService->describeMany(['TestService1', 'TestService2']);
        $this->assertEquals(2, sizeof($specs));
    }
    
    private function configureServiceManager($services = array())
    {
        $sm = new ServiceManager();
        $sm->setService('Config', [
                'valu_so' => [
                'services' => $services
                ]
                ]);
        $sm->setService('EventManager', new EventManager());
    
        return $sm;
    }    
} 