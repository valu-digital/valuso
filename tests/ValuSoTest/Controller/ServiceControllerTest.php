<?php
namespace ValuSoTest\Controller;

use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Exception\OperationNotFoundException;
use ValuSo\Exception\PermissionDeniedException;
use ValuSo\Exception\ServiceException;
use ValuSo\Broker\ServiceBroker;
use ValuSo\Controller\Plugin\ServiceBrokerPlugin;
use ValuSo\Controller\ServiceController;
use Zend\EventManager\SharedEventManager;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Controller\PluginManager;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;

class ServiceControllerTest extends TestCase
{
    public $controller;
    public $event;
    public $request;
    public $response;

    public function setUp()
    {
        $broker = new ServiceBroker();
        $broker->getLoader()->registerService(
            'TestService', 
            'Sample', 
            function($command) {
                
            switch ($command->getOperation()) {
                case 'exception':
                    throw new ServiceException(
                        "Service exception says: %MESSAGE%",
                        array('MESSAGE' => 'hello'));
                    break;
                case 'denied':
                    throw new PermissionDeniedException('Permission denied');
                    break;
                case 'nullResponse':
                    return null;
                    break;
                case 'intOneResponse':
                    return 1;
                    break;
                case 'paramTestResponse':
                    return $command->getParam('test');
                    break;
                default:
                    throw new OperationNotFoundException('Unsupported operation');
                    break;
            }
                
        });
        
        $plugin = new ServiceBrokerPlugin();
        $plugin->setServiceBroker($broker);
        
        $this->controller = new ServiceController();
        $this->controller->getPluginManager()->setService('serviceBroker', $plugin);
        
        $this->request    = new Request();
        $this->routeMatch = new RouteMatch(array('controller' => 'controller-sample'));
        $this->event      = new MvcEvent();
        $this->event->setRouteMatch($this->routeMatch);
        $this->controller->setEvent($this->event);
    }
    
    public function testServiceResponseNull()
    {
        $this->setUpRouteMatch('null-response');
        
        $this->assertEquals(
            ['d' => null],
            $this->responseJsonToArray($this->event->getResponse()));
        
        $this->assertEquals(
            200,
            $this->event->getResponse()->getStatusCode());
    }
    
    public function testServiceResponseInt()
    {
        $this->setUpRouteMatch('int-one-response');
    
        $this->assertEquals(
            ['d' => 1],
            $this->responseJsonToArray($this->event->getResponse()));
        
        $this->assertEquals(
            200,
            $this->event->getResponse()->getStatusCode());
    }

    public function testDefaultErrorHeader()
    {
        $this->setUpExceptionTest(ServiceController::HEADER_ERRORS_DEFAULT);
        
        $this->assertEquals(
            ['d' => null, 'e' => ['m' => 'Service exception says: hello', 'c' => 1006]],
            $this->responseJsonToArray($this->event->getResponse()));
        
        $this->assertEquals(
            500,
            $this->event->getResponse()->getStatusCode());
        
    }
    
    public function testVerboseErrorHeader()
    {
        $this->setUpExceptionTest(ServiceController::HEADER_ERRORS_VERBOSE);
    
        $this->assertEquals(
            ['d' => null, 'e' => ['m' => 'Service exception says: %MESSAGE%', 'a' => ['MESSAGE' => 'hello'], 'c' => 1006]],
            $this->responseJsonToArray($this->event->getResponse()));
        
        $this->assertEquals(
            500,
            $this->event->getResponse()->getStatusCode());
    }
    
    public function testPermissionDenied()
    {
        $this->setUpRouteMatch('denied');
        
        $this->assertEquals(
            403,
            $this->event->getResponse()->getStatusCode());
    }
    
    public function testServiceNotFound()
    {
        $this->routeMatch->setParam('action', 'http');
        $this->routeMatch->setParam('service', 'unknown');
        $this->routeMatch->setParam('operation', 'unknown');
        $this->controller->dispatch($this->request);
        
        $this->assertEquals(
            404,
            $this->event->getResponse()->getStatusCode());
    }
    
    public function testOperationNotFound()
    {
        $this->routeMatch->setParam('action', 'http');
        $this->routeMatch->setParam('service', 'sample');
        $this->routeMatch->setParam('operation', 'unknown');
        $this->controller->dispatch($this->request);
    
        $this->assertEquals(
            404,
            $this->event->getResponse()->getStatusCode());
    }
    
    public function testJsonRequest()
    {
        $this->request->getHeaders()->addHeaders([
            'Content-Type' => 'application/json']);
        
        $this->request->setContent('{"test": "success \'ä\'/\"ö\""}');
        
        $this->setUpRouteMatch('param-test-response');
    
        $response = $this->responseJsonToArray($this->event->getResponse());
        $this->assertEquals(
                ['d' => "success 'ä'/\"ö\""],
                $response);
    
        $this->assertEquals(
                200,
                $this->event->getResponse()->getStatusCode());
    }
    
    public function testMalformattedJsonRequest()
    {
        $this->request->getHeaders()->addHeaders([
                'Content-Type' => 'application/json']);
        
        $this->request->setContent('{"test: "success"}');
        
        $this->setUpRouteMatch('param-test-response');
        
        $this->assertEquals(
                400,
                $this->event->getResponse()->getStatusCode());
    }
    
    private function setUpExceptionTest($errorMode)
    {
        $this->request->getHeaders()->addHeaders([
            ServiceController::HEADER_ERRORS => $errorMode]);
        
        $this->setUpRouteMatch('exception', true);
    }
    
    private function setUpRouteMatch($operation, $dispatch = true)
    {
        $this->routeMatch->setParam('action', 'http');
        $this->routeMatch->setParam('service', 'sample');
        $this->routeMatch->setParam('operation', $operation);
        
        if ($dispatch) {
            $this->controller->dispatch($this->request);
        }
    }
    
    private function responseJsonToArray($response)
    {
        return json_decode($response->getContent(), true);
    }
}