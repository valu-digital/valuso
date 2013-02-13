<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Feature\ConfigurableInterface;

class ClosureService implements ConfigurableInterface
{
    public $closure;
    
    public $config;
    
    public static $default;
    
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
        
        if (!$closure && self::$default) {
            $closure = self::$default;
        }
        
        return $closure ? $closure($command) : null;
    }
    
    public function setConfig($config)
    {
        $this->config = $config;
    }
    
    public static function setDefaultClosure($default)
    {
        self::$default = $default;
    }
}