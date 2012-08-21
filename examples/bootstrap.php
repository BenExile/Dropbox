<?php

/**
 * A bootstrap for the Dropbox SDK usage examples
 * @link https://github.com/BenTheDesigner/Dropbox/tree/master/examples
 */

// Prevent access via command line interface
if (PHP_SAPI === 'cli') {
	exit('bootstrap.php must not be run via the command line interface');
}

// Don't allow direct access to the bootstrap
if(basename($_SERVER['REQUEST_URI']) == 'bootstrap.php'){
	exit('bootstrap.php does nothing on its own. Please see the examples provided');
}

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

// Check whether to use HTTPS and set the callback URL
$protocol = (!empty($_SERVER['HTTPS'])) ? 'https' : 'http';
$callback = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Instantiate the Encrypter and storage objects
$encrypter = new \Dropbox\OAuth\Storage\Encrypter('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
$storage = new \Dropbox\OAuth\Storage\Session($encrypter);

// Instantiate the persistent data store and connect
// Note: If you use this, comment out lines 39 and 50 and import oauth_tokens.sql to your database
//$userID = 1; // User ID assigned by your auth system
//$storage = new \Dropbox\OAuth\Storage\PDO($encrypter, $userID);
//$storage->connect('host', 'db', 'username', 'password');

// Instantiate the persistent data store and connect
// Note: If you use this, comment out lines 39, 44, 45 and make sure the current folder is writable.
//$userID = 1; // User ID assigned by your auth system
//$storage = new \Dropbox\OAuth\Storage\Filesystem($encrypter, $userID);

$OAuth = new \Dropbox\OAuth\Consumer\Curl($key, $secret, $storage, $callback);
$dropbox = new \Dropbox\API($OAuth);
