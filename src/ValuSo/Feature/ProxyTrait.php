<?php
namespace ValuSo\Feature;

/**
 * Simple implementation for ProxyAwareInterface with
 * convenient proxy() method
 */
trait ProxyTrait
{
    /**
     * Service proxy instance
     * 
     * @var object
     */
    protected $serviceProxy;
    
    /**
     * @see \ValuSo\Feature\ProxyAwareInterface::setServiceProxy()
     */
    public function setServiceProxy($serviceProxy)
    {
        $this->serviceProxy = $serviceProxy;
    }
    
    /**
     * Access service proxy instance
     * 
     * @return $this
     */
    protected function proxy()
    {
        return $this->proxy ? $this->proxy : $this;
    }
}