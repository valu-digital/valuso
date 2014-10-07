<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Broker\ServiceBroker;
use ValuSo\Command\CommandInterface;

class MockServiceBroker extends ServiceBroker
{
    /**
     * @var CommandInterface
     */
    public $lastCommand;
    
    public $queueOptions;
    
    public function dispatch(CommandInterface $command, $callback = null)
    {
        $this->lastCommand = $command;
        return parent::dispatch($command, $callback);
    }
    
    public function queue(CommandInterface $command, array $options = [])
    {
        $this->lastCommand = $command;
        $this->queueOptions = $options;
        return parent::queue($command, $options);
    }
}