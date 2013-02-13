<?php
namespace ValuSo\Feature;

use Valu\Service\Invoker\InvokerInterface;

/**
 * Proxy aware interface
 * 
 * This interface should be implemented by services that
 * are intended to be used via service proxy and want to
 * have access to the wrapper object.
 *
 */
interface ProxyAwareInterface
{
    /**
     * Set service proxy instance
     * 
     * @param object $serviceProxy
     */
    public function setServiceProxy($serviceProxy);
}