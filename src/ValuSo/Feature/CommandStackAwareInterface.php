<?php
namespace ValuSo\Feature;

use SplStack;

interface CommandStackAwareInterface
{
    /**
     * Set command stack
     * 
     * @param SplStack $stack
     */
    public function setCommandStack(SplStack $stack);
}