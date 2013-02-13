<?php
namespace ValuSoTest\Annotation;

use ValuSo\Annotation\ExcludePattern;

use PHPUnit_Framework_TestCase;

/**
 * ExcludePattern test case.
 */
class ExcludePatternTest extends PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $pattern = '/test/';
        $exclude = new ExcludePattern(array('value' => $pattern));
        
        $this->assertEquals($pattern, $exclude->getExcludePattern());
    }
    
    /**
     * @expectedException \ValuSo\Exception\InvalidArgumentException
     */
    public function testConstructFailsWithInvalidPattern()
    {
        $exclude = new ExcludePattern(array('value' => 'something invalid'));
    }
    
    public function testGetPattern()
    {
        $exclude = new ExcludePattern(array('value' => 'get'));
        $this->assertEquals(1, preg_match($exclude->getExcludePattern(), 'getMe'));
        
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'getme'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'get1'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'dogetMe'));
    }
    
    public function testSetPattern()
    {
        $exclude = new ExcludePattern(array('value' => 'set'));
        $this->assertEquals(1, preg_match($exclude->getExcludePattern(), 'setMe'));
        
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'setme'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'set1'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'dosetMe'));
    }
    
    public function testGetSetPattern()
    {
        $exclude = new ExcludePattern(array('value' => 'getset'));
        $this->assertEquals(1, preg_match($exclude->getExcludePattern(), 'getMe'));
        $this->assertEquals(1, preg_match($exclude->getExcludePattern(), 'setMe'));
        
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'getme'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'setme'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'set1'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'get1'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'dogetMe'));
        $this->assertEquals(0, preg_match($exclude->getExcludePattern(), 'dosetMe'));
    }

}

