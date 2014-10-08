<?php
namespace ValuSo\Feature;

use Zend\Stdlib\ArrayStack;

interface CommandStackAwareInterface
{
    /**
     * Set command stack
     * 
     * @param CommandInterface $command
     */
    public function setCommandStack(ArrayStack $stack);
}