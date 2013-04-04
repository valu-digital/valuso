<?php
/*
* Set error reporting to the level to which VALU code must comply.
*/
error_reporting( E_ALL | E_STRICT );

ini_set('display_errors', 1);

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the module root now.
 */
chdir(realpath(__DIR__));

// Setup autoloading
include './_autoload.php';