<?php
/*
* Set error reporting to the level to which Valu code must comply.
*/
error_reporting( E_ALL | E_STRICT );

// Setup autoloading
$autoloadScript = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadScript)) {
    $autoloadScript = __DIR__ . '/../../../autoload.php';
}

if (!file_exists($autoloadScript)) {
    throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}

$loader = include_once $autoloadScript;
$loader->add('ValuSoTest', __DIR__);
