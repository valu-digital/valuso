<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Annotation;
use ValuSo\Feature\ProxyAwareInterface;

/**
 * @Annotation\Version("1.0")
 * @Annotation\ExcludePattern("getset")
 */
class TestService extends AbstractService implements ProxyAwareInterface
{
    /**
     * Public property
     * @var string
     */
    public $name = 'Test.Service';
    
    /**
     * Proxy instance
     * @var TestService
     */
    public $proxy;
    
    public function __construct()
    {
        // Set proxy to self
        $this->proxy = $this;
    }
    
    /**
     * @see \ValuSo\Feature\ProxyAwareInterface::setServiceProxy()
     */
    public function setServiceProxy($serviceProxy)
    {
        $this->proxy = $serviceProxy;
    }
    
    /**
     * @Annotation\Trigger("pre");
     * @Annotation\Trigger("post");
     */
    public function deleteAll()
    {
        return true;
    }
    
    /**
     * @Annotation\Trigger("pre");
     * @Annotation\Trigger({"type":"post","args":{"job", "delayed"},"name":"post.<service>.run"})
     * @return string
     */
    public function run($job = null, $delayed = false, $debug = false)
    {
        return 'ran';
    }
    
    /**
     * @Annotation\Trigger("post");
     */
    public function update($query, array $specs = array())
    {
        $entity = new TestEntity();
        return $this->proxy->doUpdate($entity, $specs);
    }
    
    /**
     * @Annotation\Trigger({"type":"pre","name":"pre.<service>.update"})
     */
    public function doUpdate(TestEntity $entity, array $specs = array())
    {
        return true;  
    }
    
    /**
     * This setter method is not available via service layer
     * 
     * @Annotation\Exclude
     */
    public function doInternal()
    {}
    
    /**
     * This method is excluded by class level
     * exclusion pattern
     */
    public function setInternal()
    {}
    
    /**
     * Make this method available
     * 
     * @Annotation\Exclude(false)
     */
    public function getInternal()
    {}
    
    /**
     * @Annotation\Trigger("pre");
     */
    public function sharedOperation()
    {
        return 'shared';
    }
    
    /**
     * @Annotation\Inherit
     */
    public function templateOperation()
    {
        return parent::templateOperation().' modified';
    }
    
    /**
     * @Annotation\Context("http-post")
     */
    public function postOperation()
    {
        return 'posted';
    }
    
    /**
     * @Annotation\Context({"http*", "native"})
     */
    public function httpOperation()
    {
        return 'done';
    }
}