<?php
namespace ValuSoTest\Broker;

use ValuSoTest\TestAsset\TestService;

use ValuSoTest\TestAsset\ClosureService;

use ValuSo\Broker\ServicePluginManager;
use Zend\ServiceManager\ServiceManager;
use Zend\Cache\StorageFactory;
use PHPUnit_Framework_TestCase;

/**
 * ServicePluginManager test case.
 */
class ServicePluginManagerTest extends PHPUnit_Framework_TestCase
{

    /**
     *
     * @var ServicePluginManager
     */
    private $servicePluginManager;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->servicePluginManager = new ServicePluginManager();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->servicePluginManager = null;
        parent::tearDown();
    }

    /**
     * Tests ServicePluginManager->get()
     */
    public function testGet()
    {
        $service = new ClosureService();
        $this->servicePluginManager->setService('ClosureTest', $service);
        
        $this->assertSame(
            $service,
            $this->servicePluginManager->get('ClosureTest'));
    }
    
    public function testGetNonInvokable()
    {
        $service = new TestService();
        $this->servicePluginManager->setService('ProxyTest', $service);
        
        $this->assertInstanceOf(
            'ValuSoTest\TestAsset\TestService',
            $this->servicePluginManager->get('ProxyTest'));
        
        $this->assertEquals('ran', $this->servicePluginManager->get('ProxyTest')->run());
    }

    /**
     * Tests ServicePluginManager->getCreationInstanceName()
     */
    public function testGetCreationInstanceName()
    {
        // TODO Auto-generated
        // ServicePluginManagerTest->testGetCreationInstanceName()
        $this->markTestIncomplete(
                "getCreationInstanceName test not implemented");
        
        $this->servicePluginManager->getCreationInstanceName(/* parameters */);
    }

    /**
     * Tests ServicePluginManager->getCreationInstanceOptions()
     */
    public function testGetCreationInstanceOptions()
    {
        // TODO Auto-generated
        // ServicePluginManagerTest->testGetCreationInstanceOptions()
        $this->markTestIncomplete(
                "getCreationInstanceOptions test not implemented");
        
        $this->servicePluginManager->getCreationInstanceOptions(/* parameters */);
    }

    /**
     * Tests ServicePluginManager->validatePlugin()
     */
    public function testValidatePlugin()
    {
        // TODO Auto-generated ServicePluginManagerTest->testValidatePlugin()
        $this->markTestIncomplete("validatePlugin test not implemented");
        
        $this->servicePluginManager->validatePlugin(/* parameters */);
    }

    /**
     * Tests ServicePluginManager->setCache()
     */
    public function testSetCache()
    {
        // TODO Auto-generated ServicePluginManagerTest->testSetCache()
        $this->markTestIncomplete("setCache test not implemented");
        
        $this->servicePluginManager->setCache(/* parameters */);
    }

    /**
     * Tests ServicePluginManager->getCache()
     */
    public function testGetCache()
    {
        // TODO Auto-generated ServicePluginManagerTest->testGetCache()
        $this->markTestIncomplete("getCache test not implemented");
        
        $this->servicePluginManager->getCache(/* parameters */);
    }
}

