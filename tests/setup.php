<?php

/**
* Setup script for the test suite
* @todo PHPDOC
*/

// Restrict access to the command line
if (PHP_SAPI !== 'cli') {
    exit('setup.php must be run via the command line interface');
}

// Set error reporting
error_reporting(-1);
ini_set('display_errors', 'On');
session_start();

// Register a simple autoload function
spl_autoload_register(function($class){
	$class = str_replace('\\', '/', $class);
	require_once('../' . $class . '.php');
});

echo 'Running Dropbox Test Suite Setup...' . PHP_EOL;

while(empty($consumerKey)){
	echo 'Please enter your consumer key: ';
	$consumerKey = trim(fgets(STDIN));
}

while(empty($consumerSecret)){
	echo 'Please enter your consumer secret: ';
	$consumerSecret = trim(fgets(STDIN));
}

try {
	// Set up the OAuth consumer
	$storage = new \Dropbox\OAuth\Storage\Session;
	$OAuth = new \Dropbox\OAuth\Consumer\Curl($consumerKey, $consumerSecret, $storage);
	
	// Generate the authorisation URL and prompt user
	echo "Generating Authorisation URL...\r\n\r\n";
	echo "===== Begin Authorisation URL =====\r\n";
	echo $OAuth->getAuthoriseUrl() . PHP_EOL;
	echo "===== End Authorisation URL =====\r\n\r\n";
	echo "Visit the URL above and allow the SDK to connect to your account\r\n";
	echo "Press any key once you have completed this step...";
	fgets(STDIN);
	
	// Acquire and store the access token
	echo "Acquiring access token...\r\n";
	$OAuth->getAccessToken();
	$token = serialize($storage->get());
	file_put_contents('oauth.token', $token);
	exit('Setup complete! You can now run the test suite.');
} catch(\Dropbox\Exception $e) {
	// Exit, displaying the Exception message
	exit($e->getMessage());
}
