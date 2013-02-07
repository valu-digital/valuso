<?php
namespace ValuSo\Feature;

use ValuSo\Broker\ServiceWrapper;

interface ServiceWrapperAwareInterface
{
    public function setServiceWrapper(ServiceWrapper $serviceWrapper);
}