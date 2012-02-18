<?php

/**
 * Upload a file to the authenticated user's Dropbox
 * @link https://www.dropbox.com/developers/reference/api#files_put
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L112-127
 */

// Require the bootstrap
require_once('bootstrap.php');

// Open a stream for reading
$data = 'This file was uploaded using the Dropbox API!';
$stream = fopen('data://text/plain,' . $data, 'r');

// Upload the stream data to the specified filename
$put = $dropbox->putStream($stream, 'api_upload_test.txt');

// Dump the output
var_dump($put);
