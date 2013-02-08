<?php
namespace ValuSo\Invoker;

use ValuSo\Command\CommandInterface;

interface InvokerInterface
{
    public function invoke($service, CommandInterface $c);
}