<?php

// Don't allow direct access to the boostrap
//if(basename($_SERVER['REQUEST_URI']) == 'bootstrap.php'){
///	exit('bootstrap.php does nothing on its own. Please see the examples provided');
//}

// Set error reporting
error_reporting(-1);
ini_set('display_errors', 'On');
ini_set('html_errors', 'On');

// Register a simple autoload function
spl_autoload_register(function($class){
	$class = str_replace('\\', '/', $class);
	require_once('../' . $class . '.php');
});

// Set your consumer key, secret and callback URL
$key      = 'XXXXXXXXXXXXXXX';
$secret   = 'XXXXXXXXXXXXXXX';
$callback = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Instantiate the required Dropbox objects
$storage = new \Dropbox\OAuth\Storage\Session;
$OAuth = new \Dropbox\OAuth\Consumer\Curl($key, $secret, $storage, $callback);
$dropbox = new \Dropbox\API($OAuth);
