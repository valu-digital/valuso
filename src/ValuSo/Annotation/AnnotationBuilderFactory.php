<?php
namespace ValuSo\Annotation;

use ValuSo\Util\EventManagerConfigurator;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Code\Annotation\Parser;


/**
 * AnnotationBuilder factory
 *
 */
class AnnotationBuilderFactory implements FactoryInterface
{

    /**
     * Create an annotation builder for service broker
     * 
     * {@see ValuSo\Broker\ServiceBroker} uses {@see Zend\ServiceManager\ServiceManager} internally to initialize service
     * instances. {@see Zend\Mvc\Service\ServiceManagerConfig} for how to configure service manager.
     * 
     * This factory uses following configuration scheme:
     * <code>
     * [
     *   'valu_so' => [
     *       'annotations' => [
     *           '<id>' => '',
     *       ],
     *       'annotation_listeners' => [
     *           '<ServiceID>'
     *       ]
     *   ]
     * ]
     * </code>
     * 
     * @see \Zend\ServiceManager\FactoryInterface::createService()
     * @return ServiceBroker
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config');
        $config = empty($config['valu_so']) ? [] : $config['valu_so'];
        
        $annotationBuilder = new AnnotationBuilder();
        
        // Attach custom annotations
        if (!empty($config['annotations'])) {
            $parser = new Parser\DoctrineAnnotationParser();
            
            foreach($config['annotations'] as $name => $class){
                $parser->registerAnnotation($class);
            }
            
            $annotationBuilder->getAnnotationManager()->attach($parser);
        }
        
        // Attach listeners for custom annotations
        if (!empty($config['annotation_listeners'])) {
            EventManagerConfigurator::configure(
                $annotationBuilder->getEventManager(), 
                $serviceLocator, 
                $config['annotation_listeners']);
        }
        
        return $annotationBuilder;
    }
    
}