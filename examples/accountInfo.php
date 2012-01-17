<?php

require_once('bootstrap.php');

// Retrieve information about the authenticated user's account
// @link https://www.dropbox.com/developers/reference/api#account-info

$accountInfo = $dropbox->accountInfo();
var_dump($accountInfo);
