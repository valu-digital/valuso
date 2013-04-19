<?php
namespace ValuSo\Annotation\Listener;

use ValuSo\Annotation;
use Zend\EventManager\EventManagerInterface;

class OperationAnnotationsListener extends AbstractAnnotationsListener
{
    /**
     * Attach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach('configureOperation', array($this, 'handleExcludeAnnotation'));
        $this->listeners[] = $events->attach('configureOperation', array($this, 'handleContextAnnotation'));
        $this->listeners[] = $events->attach('configureOperation', array($this, 'handleInheritAnnotation'));
        $this->listeners[] = $events->attach('configureOperation', array($this, 'handleTriggerAnnotation'));
        $this->listeners[] = $events->attach('configureOperation', array($this, 'handleAliasAnnotation'));
    }
    
    /**
     * Handle inheritance annotation
     *
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleInheritAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\Inherit) {
            return;
        }
    
        $operationSpec = $e->getParam('operationSpec');
        $operationSpec['inherit'] = true;
    }
    
    /**
     * Handle event trigger annotation
     * 
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleTriggerAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\Trigger) {
            return;
        }

        $operationSpec = $e->getParam('operationSpec');
        $operationSpec['events'][] = $annotation->getEventDescription();
    }
    
    /**
     * Handle alias annotation
     *
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleAliasAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\Alias) {
            return;
        }
    
        $operationSpec = $e->getParam('operationSpec');
        $operationSpec['aliases'] = (array) $annotation->getAlias();
    }
    
    protected function getEventParamName()
    {
        return 'operationSpec';
    }
}