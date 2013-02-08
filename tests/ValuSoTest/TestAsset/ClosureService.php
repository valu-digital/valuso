<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Feature\ConfigurableInterface;

class ClosureService implements ConfigurableInterface
{
    public $closure;
    
    public $config;
    
    public function __construct($options = null)
    {
        if ($options instanceof \Closure) {
            $this->closure = $options;
        } else {
            $this->setConfig($options);
        }
    }
    
    public function __invoke($command)
    {
        $closure = $this->closure;
        return $closure ? $closure($command) : null;
    }
    
    public function setConfig($config)
    {
        $this->config = $config;
    }
}