<?php
namespace ValuSoTest\Command;

use Zend\EventManager\ResponseCollection;
use PHPUnit_Framework_TestCase;
use ValuSo\Command\Command;

/**
 * Command test case.
 */
class CommandTest extends PHPUnit_Framework_TestCase
{

    /**
     *
     * @var Command
     */
    private $command;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        
        // TODO Auto-generated CommandTest::setUp()
        
        $this->command = new Command();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        // TODO Auto-generated CommandTest::tearDown()
        $this->command = null;
        
        parent::tearDown();
    }

    /**
     * Tests Command->setParams()
     */
    public function testSetParams()
    {
        $this->assertSame(
            $this->command,
            $this->command->setParams(['first' => 'one', 'second' => 'two']));
    }

    /**
     * Tests Command->getParams()
     * 
     * @depends testSetParams
     */
    public function testGetParams()
    {
        $data = ['first' => 'one', 'second' => 'two'];
        
        $this->command->setParams($data);
        $params = $this->command->getParams();
        
        $this->assertInstanceOf(
            '\ArrayObject',
            $params);
        
        
        $this->assertEquals(
            $data,
            $params->getArrayCopy());
    }
    
    public function testSetGetService()
    {
        $name = 'service';
        
        $this->assertSame(
            $this->command,
            $this->command->setService($name));
        
        $this->assertEquals(
            $name,
            $this->command->getService($name));
    }

    public function testSetGetOperation()
    {
        $name = 'operation';
        
        $this->assertSame(
            $this->command,
            $this->command->setOperation($name));
        
        $this->assertEquals(
            $name,
            $this->command->getOperation($name));
    }

    public function testSetGetContext()
    {
        $name = 'context';
        
        $this->assertSame(
            $this->command,
            $this->command->setContext($name));
        
        $this->assertEquals(
            $name,
            $this->command->getContext($name));
    }

    public function testSetGetResponses()
    {
        $responses = new ResponseCollection();
        
        $this->assertSame(
            $this->command,
            $this->command->setResponses($responses));
        
        $this->assertSame($responses, $this->command->getResponses());
    }
}

