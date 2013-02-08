<?php
/*
* Set error reporting to the level to which VALU code must comply.
*/
error_reporting( E_ALL | E_STRICT );

ini_set('display_errors', 1);

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(realpath(dirname(__DIR__)));

// Setup autoloading
include __DIR__ . '/_autoload.php';