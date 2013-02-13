<?php
namespace ValuSo\Command;

use Zend\Stdlib\CallbackHandler;

/**
 * Lazy callback handler
 *
 * Lazy callback handler accepts late registration of the callback.
 * @see \Zend\Stdlib\CallbackHandler for details
 */
class LazyCallbackHandler extends CallbackHandler
{
    
    public function __construct($callback = null, array $metadata = array())
    {
        $this->metadata  = $metadata;
        
        if ($callback) {
            $this->registerCallback($callback);
        }
    }
    
    /**
     * Register callback
     * 
     * @param callable $callback
     */
    public function setCallback($callback)
    {
        $this->registerCallback($callback);
    }
}