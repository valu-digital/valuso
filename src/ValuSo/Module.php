<?php
namespace ValuSo;

use Zend\ModuleManager\Feature;
use Zend\EventManager\EventInterface;
use Zend\Loader\AutoloaderFactory;
use Zend\Loader\StandardAutoloader;
use Zend\Console\Adapter\AdapterInterface as Console;

class Module
    implements  Feature\AutoloaderProviderInterface, 
                Feature\ConfigProviderInterface,
                Feature\ConsoleUsageProviderInterface
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
            AutoloaderFactory::STANDARD_AUTOLOADER => array(
                StandardAutoloader::LOAD_NS => array(
                    __NAMESPACE__ => __DIR__,
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
        return include __DIR__ . '/../../config/module.config.php';
    }
    
    public function getConsoleUsage(Console $console){
        return array(
                // Describe available commands
                'Using Valu services:',
                'exec [OPTION] SERVICE OPERATION [PARAMS]' => 'Execute a service operation',
    
                // Describe expected parameters
                array( 'SERVICE', 'Name of the service' ),
                array( 'OPERATION', 'Name of the operation' ),
                array( 'PARAMS', 'Additional parameters', "JSON formatted query string \nRemember to use single quotes to escape JSON and double quotes to escape param names. \nExample: ./valu.php exec user create '{\"username\":\"admin\"}'" ),
                array( '--user|-u', 'Username', 'If username is omitted, it will be prompted'),
                array( '--password|-p', 'Password', 'If password is omitted, it will be prompted'),
                array( '--verbose|-v', 'Verbose mode', 'Display additional error information' ),
                array( '--silent|-s', 'Silent mode', 'Do not display output messages' ),
        );
    }
}