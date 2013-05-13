<?php
namespace ValuSo\Util;

use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\EventManager\ListenerAggregateInterface;

class EventManagerConfigurator
{
    /**
     * Attach listeners from array
     * 
     * @param array $listeners
     */
    public static function configure(EventManagerInterface $eventManager, ServiceLocatorInterface $serviceLocator, array $listeners)
    {
        if (!empty($listeners)) {
            foreach ($listeners as $key => $value) {
        
                if ($value instanceof ListenerAggregateInterface) {
                    $listener = $value;
                } elseif(is_string($value) && $serviceLocator->has($value)) {
                    $listener = $serviceLocator->get($value);
                } elseif(is_string($value) && class_exists($value)) {
                    $listener = new $value();
                } else {
                    $listener = $value;
                }
        
                if ($listener instanceof ListenerAggregateInterface) {
                    $eventManager->attachAggregate($listener);
                } elseif (isset($listener['event']) && isset($listener['callback'])) {
                    $eventManager->attach($listener['event'], $listener['callback']);
                } else {
                    throw new InvalidListenerException(
                            sprintf("Listener for service ID %s doesn't implement ListenerAggregateInterface", $value));
                }
            }
        }
    }
}