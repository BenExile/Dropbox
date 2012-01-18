<?php

/**
 * Retrieve information about the authenticated user's account
 * @link https://www.dropbox.com/developers/reference/api#account-info
 */

// Require the bootstrap
require_once('bootstrap.php');

// Retrieve the information and output
$accountInfo = $dropbox->accountInfo();

// Dump the output
var_dump($accountInfo);
