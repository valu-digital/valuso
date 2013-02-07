<?php
namespace ValuSo\Feature;

use ValuSo\Broker\ServiceBroker;

interface ServiceBrokerAwareInterface
{
    public function setServiceBroker(ServiceBroker $serviceBroker);
}