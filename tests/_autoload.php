<?php
if (
    !($loader = @include_once __DIR__ . '/../../../autoload.php')
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

