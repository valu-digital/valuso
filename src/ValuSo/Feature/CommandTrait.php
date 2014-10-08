<?php
namespace ValuSo\Feature;

use ValuSo\Command\CommandInterface;
use Zend\Stdlib\ArrayStack;

trait CommandTrait
{
    /**
     * Command stack
     * 
     * @var ArrayStack
     */
    protected $commandStack;
    
    /**
     * Set command stack
     * 
     * @param CommandInterface $command
     */
    public function setCommandStack(ArrayStack $stack)
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
        return $this->commandStack->getIterator()->current();
    }
}