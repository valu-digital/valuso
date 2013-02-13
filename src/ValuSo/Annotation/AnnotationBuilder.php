<?php
namespace ValuSo\Annotation;

use Zend\Code\Reflection\MethodReflection;

use ValuSo\Exception;
use ArrayObject;
use Zend\Code\Reflection\ClassReflection;
use Zend\Code\Annotation\AnnotationCollection;
use Zend\Code\Annotation\AnnotationManager;
use Zend\Code\Annotation\Parser;

class AnnotationBuilder
{
    
    /**
     * Annotation manager
     * 
     * @var \ValuSo\Annotation\AnnotationManager
     */
    private $annotationManager;
    
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
    
        $serviceSpec       = new ArrayObject();
        $operationSpec     = new ArrayObject();
    
        $reflection  = new ClassReflection($entity);
        $this->parseClassSpecifications($reflection, $serviceSpec, $operationSpec);
        $serviceSpec['operations'] = $operationSpec;
        
        return $serviceSpec;
    }
    
    protected function parseClassSpecifications(ClassReflection $class, ArrayObject $serviceSpec, ArrayObject $operationSpec)
    {
        if ($class->getParentClass()) {
            $this->parseClassSpecifications($class->getParentClass(), $serviceSpec, $operationSpec);
        }
        
        $annotationManager = $this->getAnnotationManager();
        $annotations = $class->getAnnotations($annotationManager);
        
        if (!array_key_exists('version', $serviceSpec)) {
            $serviceSpec['version'] = null;
        }
        
        // Each class must define its own exclusion pattern
        $serviceSpec['exclude_pattern'] = null;
        
        if ($annotations instanceof AnnotationCollection) {
            $this->configureService($annotations, $class, $serviceSpec);
        }
        
        foreach ($class->getMethods() as $method) {
            $annotations = $method->getAnnotations($annotationManager);
        
            if ($annotations instanceof AnnotationCollection) {
                $this->configureOperation($annotations, $method, $serviceSpec, $operationSpec);
            }
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
     * Configure service based on annotations
     * 
     * @param AnnotationCollection $annotations
     * @param ClassReflection $reflection
     * @param ArrayObject $serviceSpec
     */
    protected function configureService(AnnotationCollection $annotations, 
                                        ClassReflection $reflection, ArrayObject $serviceSpec)
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Version) {
                $serviceSpec['version'] = $annotation->getVersion();
            } elseif ($annotation instanceof ExcludePattern) {
                $serviceSpec['exclude_pattern'] = $annotation->getExcludePattern();
            }
        }
    }
    
    /**
     * Configure operation based on annotations
     * 
     * @param AnnotationCollection $annotations
     * @param MethodReflection $method
     * @param ArrayObject $serviceSpec
     * @param ArrayObject $operationSpec
     */
    protected function configureOperation(AnnotationCollection $annotations, 
                                          MethodReflection $method, ArrayObject $serviceSpec, 
                                          ArrayObject $operationSpec)
    {
        $operation = new ArrayObject(array(
            'events' => array(), 
            'context' => '*')
        );
        
        $excludePattern = $serviceSpec['exclude_pattern'];
        $exclude = null;
        
        foreach ($annotations as $annotation) {
        
            // Use operation level exclude
            if ($annotation instanceof Exclude) {
                $exclude = $annotation->getExclude();
                break;
            }
            
            // Use existing definitions
            if ($annotation instanceof Inherit) {
                return;
            }
        }
        
        // Fallback: class level exclusion pattern
        if ($exclude === null && $excludePattern && preg_match($excludePattern, $method->getName())) {
            $exclude = true;
        }
        
        // Skip this method, if excluded
        if ($exclude) {
            return;
        }
        
        foreach ($annotations as $annotation) {

            if ($annotation instanceof Trigger) {
                $specs = $annotation->getTrigger();
                
                $event = array(
                    'type' => null,        
                    'name' => null,
                    'args' => null  
                );
                
                if (is_string($specs)) {
                    $event['type'] = $specs;
                } else {
                    $event['type'] = isset($specs['type']) ? $specs['type'] : null;
                    $event['name'] = isset($specs['name']) ? $specs['name'] : null;
                    $event['args'] = isset($specs['args']) ? $specs['args'] : null;
                }
                
                $operation['events'][] = $event;
            } elseif ($annotation instanceof Context) {
                $operation['context'] = $annotation->getContext();
            }
        }
        
        $operationSpec[$method->getName()] = $operation;
    }
}