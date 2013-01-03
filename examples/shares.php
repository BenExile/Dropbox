<?php

/**
 * Creates and returns a Dropbox link to files or folders
 * @link https://www.dropbox.com/developers/reference/api#shares
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php
 */

// Require the bootstrap
require_once('bootstrap.php');

// Get the Dropbox link
$shares = $dropbox->shares('api_upload_test.txt', false);

// Dump the output
var_dump($shares);
