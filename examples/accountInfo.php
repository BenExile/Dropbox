<?php

/**
 * Retrieve information about the authenticated user's account
 * @link https://www.dropbox.com/developers/reference/api#account-info
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L70-78
 */

// Require the bootstrap
require_once('bootstrap.php');

try {
	// Attempt to retrieve the account information
	$accountInfo = $dropbox->accountInfo();
} catch (\Dropbox\Exception $e) {
	if ($e->getCode() == 401) {
		// If the token is invalid, delete it and re-authenticate
		$storage->delete();
		header('Location: ' . $callback);
	}
}

// Dump the output
var_dump($accountInfo);
