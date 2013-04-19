<?php
namespace ValuSo\Annotation\Listener;

use ValuSo\Annotation;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

/**
 * Base annotations listener.
 *
 * Provides detach() implementation.
 */
abstract class AbstractAnnotationsListener implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();
    
    /**
     * Handle exclude annotation
     *
     * @param \Zend\EventManager\EventInterface $e
     * @param string $param
     * @return void
     */
    public function handleExcludeAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\Exclude) {
            return;
        }
    
        $spec = $e->getParam($this->getEventParamName());
        $spec['exclude'] = $annotation->getExclude();
    }
    
    /**
     * Handle context annotation
     *
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleContextAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\Context) {
            return;
        }
    
        $spec = $e->getParam($this->getEventParamName());
        $spec['contexts'] = $annotation->getContext();
    }

    /**
     * Detach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if (false !== $events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }
    
    /**
     * Retrieve event parameter name
     * 
     * @return string
     */
    protected abstract function getEventParamName();
}