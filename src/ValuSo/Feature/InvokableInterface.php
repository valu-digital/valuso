<?php
namespace ValuSo\Feature;

use ValuSo\Command\Command;

/**
 * Invokable service
 * 
 */
interface InvokableInterface
{
    public function __invoke(Command $command);
}