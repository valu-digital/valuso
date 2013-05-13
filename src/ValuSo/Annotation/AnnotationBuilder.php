<?php
namespace ValuSo\Annotation;

use ValuSo\Exception\AnnotationException;
use ValuSo\Annotation\Listener\ServiceAnnotationsListener;
use ValuSo\Annotation\Listener\OperationAnnotationsListener;
use ValuSo\Exception;
use ArrayObject;
use Zend\Code\Reflection\ClassReflection;
use Zend\Code\Annotation\AnnotationCollection;
use Zend\Code\Annotation\AnnotationManager;
use Zend\Code\Annotation\Parser;
use Zend\Code\Reflection\MethodReflection;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\Event;
use Zend\EventManager\EventManager;

class AnnotationBuilder implements EventManagerAwareInterface
{
    
    /**
     * Annotation manager
     * 
     * @var \ValuSo\Annotation\AnnotationManager
     */
    private $annotationManager;
    
    /**
     * @var EventManagerInterface
     */
    protected $events;
    
    /**
     * @var array Default annotations to register
     */
    protected $defaultAnnotations = array(
        'Exclude',
        'ExcludePattern',
        'Version',
        'Trigger',
        'Inherit',
        'Context',
        'Alias',
    );
    
    /**
     * Creates and returns service definition as an array object
     *
     * @param  string|object $entity Either an instance or a valid class name for an entity
     * @throws Exception\InvalidArgumentException if $entity is not an object or class name
     * @return ArrayObject
     */
    public function getServiceSpecification($entity)
    {
        if (!is_object($entity)) {
            if ((is_string($entity) && (!class_exists($entity))) // non-existent class
                    || (!is_string($entity)) // not an object or string
            ) {
                throw new Exception\InvalidArgumentException(sprintf(
                        '%s expects an object or valid class name; received "%s"',
                        __METHOD__,
                        var_export($entity, 1)
                ));
            }
        }
    
        $serviceSpec = new ArrayObject();
        $serviceSpec['operations'] = new ArrayObject();
    
        $reflection  = new ClassReflection($entity);
        $this->parseClassSpecifications($reflection, $serviceSpec);
        
        return $serviceSpec;
    }
    
    /**
     * Parse class level specs for service
     * 
     * @param ClassReflection $class
     * @param ArrayObject $serviceSpec
     */
    protected function parseClassSpecifications(ClassReflection $class, ArrayObject $serviceSpec)
    {
        if ($class->getParentClass()) {
            $this->parseClassSpecifications($class->getParentClass(), $serviceSpec);
        }
        
        $annotationManager = $this->getAnnotationManager();
        $annotations = $class->getAnnotations($annotationManager);
        
        if (!array_key_exists('version', $serviceSpec)) {
            $serviceSpec['version'] = null;
        }
        
        // Each class must define its own exclusion pattern
        $serviceSpec['exclude_patterns'] = array();
        $serviceSpec['exclude'] = false;
        $serviceSpec['contexts'] = array('native');
        
        if ($annotations instanceof AnnotationCollection) {
            $this->configureService($annotations, $class, $serviceSpec);
        }
        
        // Exit here if service is excluded
        if ($serviceSpec['exclude']) {
            return;
        }
        
        foreach ($class->getMethods() as $method) {
            
            // Skip method if it is not owned by this class
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            
            // Skip method declared in Trait due to ZF2 bug
            if ($this->isDeclaredInTrait($method)) {
                continue;
            }
            
            try{
                $annotations = $method->getAnnotations($annotationManager);
            } catch(\Doctrine\Common\Annotations\AnnotationException $e) {
                throw new AnnotationException(
                    'Error parsing annotation for class "'.$class->getName().'::'.$method->getName().'"', 0, $e);
            } catch(\Exception $e) {
                // Due to ZF2 bug with parsing annotations for traits, skip related error
                if (strpos($e->getMessage(), 'Argument 3 passed to Zend\Code\Scanner\AnnotationScanner') === 0) {
                    continue;
                }
                
                throw $e;
            }
            
            if (!$annotations instanceof AnnotationCollection) {
                $annotations = new AnnotationCollection();
            }
        
            $this->configureOperation($annotations, $method, $serviceSpec);
        }
    }
    
    /**
     * Retrieve annotation manager
     *
     * If none is currently set, creates one with default annotations.
     *
     * @return AnnotationManager
     */
    public function getAnnotationManager()
    {
        if ($this->annotationManager) {
            return $this->annotationManager;
        }

        $this->setAnnotationManager(new AnnotationManager());
        return $this->annotationManager;
    }
    
    /**
     * Set annotation manager to use when building form from annotations
     *
     * @param  AnnotationManager $annotationManager
     * @return AnnotationBuilder
     */
    public function setAnnotationManager(AnnotationManager $annotationManager)
    {
        $parser = new Parser\DoctrineAnnotationParser();
        foreach ($this->defaultAnnotations as $annotationName) {
            $class = __NAMESPACE__ . '\\' . $annotationName;
            $parser->registerAnnotation($class);
        }
        $annotationManager->attach($parser);
        $this->annotationManager = $annotationManager;
        return $this;
    }
    
    /**
     * Get event manager
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (null === $this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }
    
    /**
     * Set event manager instance
     *
     * @param  EventManagerInterface $events
     * @return AnnotationBuilder
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_class($this),
        ));
        $events->attach(new OperationAnnotationsListener());
        $events->attach(new ServiceAnnotationsListener());
        $this->events = $events;
        return $this;
    }
    
    /**
     * Configure service based on annotations
     * 
     * @param AnnotationCollection $annotations
     * @param ClassReflection $reflection
     * @param ArrayObject $serviceSpec
     */
    protected function configureService(AnnotationCollection $annotations, 
                                        ClassReflection $reflection, ArrayObject $serviceSpec)
    {
        $events = $this->getEventManager();
        foreach ($annotations as $annotation) {
            $events->trigger(__FUNCTION__, $this, array(
                    'annotation'  => $annotation,
                    'name'        => $reflection->getName(),
                    'serviceSpec' => $serviceSpec
            ));
        }
    }
    
    /**
     * Configure operation based on annotations
     * 
     * @param AnnotationCollection $annotations
     * @param MethodReflection $method
     * @param ArrayObject $serviceSpec
     */
    protected function configureOperation(AnnotationCollection $annotations, 
                                          MethodReflection $method, ArrayObject $serviceSpec)
    {
        // Skip if no annotations are present
        if (!$annotations->count()) {
            return;
        }
        
        $operationSpec = new ArrayObject(array(
            'events' => array(), 
            'contexts' => $serviceSpec['contexts'],
            'aliases' => array(),
            'inherit' => false,
            'exclude' => null)
        );
        
        $events = $this->getEventManager();
        
        $event = new Event();
        $event->setParams(array(
            'name'        => $method->getName(),
            'serviceSpec' => $serviceSpec,
            'operationSpec' => $operationSpec
        ));
        
        foreach ($annotations as $annotation) {
            $event->setParam('annotation', $annotation);
            $events->trigger(__FUNCTION__, $this, $event);
        }
        
        // Skip annotations if inherit flag is set
        if ($operationSpec['inherit'] === true) {
            return;
        }
        
        // Do not configure operation if it is excluded on
        // service or operation level
        $excludePatterns = $serviceSpec['exclude_patterns'];

        if ($operationSpec['exclude'] === true) {
            return;
        } elseif ($operationSpec['exclude'] === null && sizeof($excludePatterns)) {
            foreach ($excludePatterns as $excludePattern) {
               if (preg_match($excludePattern, $method->getName())) {
                   return;
               }
            }
        }
        
        unset($operationSpec['exclude']);
        
        // Configure operation (overwrites any existing configurations
        // for this operation)
        $serviceSpec['operations'][$method->getName()] = $operationSpec;
    }
    
    protected function isDeclaredInTrait(MethodReflection $method)
    {
        if ($method->getDeclaringClass()->getFileName() !== $method->getFileName()) {
            return true;
        } else {
            return false;
        }
    }
}