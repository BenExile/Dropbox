<?php

/**
 * A bootstrap for the Dropbox SDK unit tests
 * @link https://github.com/BenTheDesigner/Dropbox/tree/master/tests
 */

// Restrict access to the command line
if (PHP_SAPI !== 'cli') {
	exit('setup.php must be run via the command line interface');
}

// Set error reporting
error_reporting(-1);
ini_set('display_errors', 'On');
ini_set('html_errors', 'Off');
session_start();

// Register a simple autoload function
spl_autoload_register(function($class){
	$class = str_replace('\\', '/', $class);
	require_once('../' . $class . '.php');
});
