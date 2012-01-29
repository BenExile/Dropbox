<?php

/**
 * Retrieve information about the authenticated user's account
 * @link https://www.dropbox.com/developers/reference/api#account-info
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L70-78
 */

// Require the bootstrap
require_once('bootstrap.php');

// Retrieve the account information
$accountInfo = $dropbox->accountInfo();

// Dump the output
var_dump($accountInfo);
