<?php

/**
* This setup script will acquire and store an
* access token which can be used by the unit test suite
* @link https://github.com/BenTheDesigner/Dropbox/tree/master/tests
*/

// Require the bootstrap
require_once('bootstrap.php');

echo 'Running Dropbox Test Suite Setup...' . PHP_EOL;

// Check if a token is already stored
if(file_exists('oauth.token')){
	$token = file_get_contents('oauth.token');
	if(unserialize($token) !== false){
		// Prompt for use input
		while(empty($response)){
			echo 'Do you wish to delete the stored access token? (y/n): ';
			$response = strtolower(trim(fgets(STDIN)));
		}
		// Exit if user chooses not to delete the token
		if($response != 'y' && $response != 'yes'){
			exit('Quitting setup. Your access token was not deleted.');
		} else {
			unlink('oauth.token');
			echo 'Access token deleted. Continuing setup.' . PHP_EOL;
		}
	}
}

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
	
	// Acquire the access token
	echo "Acquiring access token...\r\n";
	
	$OAuth->getAccessToken();
	$token = serialize(array(
		'token' => $storage->get('access_token'),
		'consumerKey' => $consumerKey,
		'consumerSecret' => $consumerSecret,
	));
	
	// Write the access token to disk
	if(@file_put_contents('oauth.token', $token) === false){
		throw new \Dropbox\Exception('Unable to write token to file');
	} else {
		exit('Setup complete! You can now run the test suite.');
	}
} catch(\Dropbox\Exception $e) {
	echo $e->getMessage() . PHP_EOL;
	exit('Setup failed! Please try running setup again.');
}
