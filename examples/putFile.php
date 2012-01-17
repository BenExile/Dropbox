<?php

/**
 * Upload a file to the authenticated user's Dropbox
 * @link https://www.dropbox.com/developers/reference/api#files-POST
 */

// Require the bootstrap
require_once('bootstrap.php');

// Create a temporary file and write some data to it
$tmp = tempnam('/tmp', 'dropbox');
$data = 'This file was uploaded at '. date('H:i:s') .' using the Dropbox API';
file_put_contents($tmp, $data);

// Upload the file with an alternative filename
$put = $dropbox->putFile($tmp, 'api_upload_test.txt');
var_dump($put);

// Unlink the temporary file
unlink($tmp);
