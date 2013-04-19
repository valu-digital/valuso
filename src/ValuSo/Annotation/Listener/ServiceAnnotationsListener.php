<?php
namespace ValuSo\Annotation\Listener;

use ValuSo\Annotation;
use Zend\EventManager\EventManagerInterface;

class ServiceAnnotationsListener extends AbstractAnnotationsListener
{
    /**
     * Attach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach('configureService', array($this, 'handleExcludeAnnotation'));
        $this->listeners[] = $events->attach('configureService', array($this, 'handleContextAnnotation'));
        $this->listeners[] = $events->attach('configureService', array($this, 'handleExcludePatternAnnotation'));
        $this->listeners[] = $events->attach('configureService', array($this, 'handleVersionAnnotation'));
    }

    /**
     * Handle exclude annotation
     *
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleExcludePatternAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\ExcludePattern) {
            return;
        }
    
        $serviceSpec = $e->getParam('serviceSpec');
        $serviceSpec['exclude_patterns'] = (array) $annotation->getExcludePattern();
    }
    
    /**
     * Handle inheritance annotation
     *
     * @param \Zend\EventManager\EventInterface $e
     * @return void
     */
    public function handleVersionAnnotation($e)
    {
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Annotation\Version) {
            return;
        }
    
        $serviceSpec = $e->getParam('serviceSpec');
        $serviceSpec['version'] = $annotation->getVersion();
    }
    
    protected function getEventParamName()
    {
        return 'serviceSpec';
    }
}