<?php
/**
 * Setup autoloading
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    include_once __DIR__ . '/../vendor/autoload.php';
} else {
    $zfPath = __DIR__ . '/../../../vendor/ZF2';
    
    // if composer autoloader is missing, explicitly add the ZF library path
    require_once $zfPath . '/library/Zend/Loader/StandardAutoloader.php';
    
    $loader = new Zend\Loader\StandardAutoloader(
        array(
             Zend\Loader\StandardAutoloader::LOAD_NS => array(
                 'Zend'     => $zfPath . '/library/Zend',
                 'ValuSo'   => __DIR__ . '/../src/ValuSo',
                 'ValuSoTest' => __DIR__ . '/ValuSoTest',
             ),
        ));
    $loader->register();
    
    unset($zfPath);
}