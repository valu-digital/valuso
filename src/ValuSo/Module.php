<?php
namespace ValuSo;

use Zend\ModuleManager\Feature;
use Zend\EventManager\EventInterface;

class Module
    implements  Feature\AutoloaderProviderInterface, 
                Feature\ConfigProviderInterface
{

    /**
     * getAutoloaderConfig() defined by AutoloaderProvider interface.
     * 
     * @see AutoloaderProvider::getAutoloaderConfig()
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__
                ),
            ),
        );
    }

    /**
     * getConfig implementation for ConfigListener
     * 
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}