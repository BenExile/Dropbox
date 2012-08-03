<?php

/**
 * Uploads large files to Dropbox in mulitple chunks
 * @link https://www.dropbox.com/developers/reference/api#chunked-upload
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L122-139
 */

// Require the bootstrap
require_once('bootstrap.php');

// Extend your sript execution time where required
set_time_limit(0);

// Upload the large file
$largeFilePath = 'path/to/large/file';
$chunked = $dropbox->chunkedUpload($largeFilePath);

// Dump the output
var_dump($chunked);
