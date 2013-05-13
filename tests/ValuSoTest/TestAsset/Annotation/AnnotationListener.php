<?php
namespace ValuSoTest\TestAsset\Annotation;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

class AnnotationListener implements ListenerAggregateInterface
{
    /**
     * Attach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach('configureOperation', array($this, 'handleTestAnnotation'));
    }
    
    public function detach(EventManagerInterface $events)
    {
    }
    
    public function handleTestAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Test) {
            return;
        }
        
        $operationSpec = $e->getParam('operationSpec');
        $operationSpec['test'] = $annotation->getValue();
    }
}