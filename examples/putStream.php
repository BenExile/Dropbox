<?php

/**
 * Upload a file to the authenticated user's Dropbox
 * @link https://www.dropbox.com/developers/reference/api#files_put
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L112-127
 */

// Require the bootstrap
require_once('bootstrap.php');

// Open a stream for reading and writing
$stream = fopen('php://temp', 'rw');

// Write some data to the stream
$data = 'This file was uploaded using the Dropbox API!';
fwrite($stream, $data);

// Upload the stream data to the specified filename
$put = $dropbox->putStream($stream, 'api_upload_test.txt');

// Close the stream
fclose($stream);

// Dump the output
var_dump($put);
