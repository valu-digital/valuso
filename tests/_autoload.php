<?php
if (
    !($loader = @include_once '../autoload.php')
    && !@($loader = include_once '../../vendor/autoload.php')
) {
    throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}

$loader = new \Zend\Loader\StandardAutoloader(
    array(
        Zend\Loader\StandardAutoloader::LOAD_NS => array(
            'ValuSo'   => __DIR__ . '/../src/ValuSo',
            'ValuSoTest' => __DIR__ . '/ValuSoTest',
        ),
    ));
$loader->register();

