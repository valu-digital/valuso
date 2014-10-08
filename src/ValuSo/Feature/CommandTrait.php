<?php
namespace ValuSo\Feature;

use ValuSo\Command\CommandInterface;
use SplStack;

trait CommandTrait
{
    /**
     * Command stack
     * 
     * @var SplStack
     */
    protected $commandStack;
    
    /**
     * Set command stack
     * 
     * @param CommandInterface $command
     */
    public function setCommandStack(SplStack $stack)
    {
        $this->commandStack = $stack;
    }
    
    /**
     * Retrieve command stack
     * 
     * @return ArrayStack
     */
    public function getCommandStack()
    {
        if (!$this->commandStack) {
            $this->commandStack = new SplStack();
        }
        
        return $this->commandStack;
    }
    
    /**
     * Retrieve command for current operation
     * 
     * Command for current operation is the
     * topmost item in the stack.
     * 
     * @return CommandInterface
     */
    public function getCommand()
    {
        return $this->getCommandStack()->getIterator()->current();
    }
}