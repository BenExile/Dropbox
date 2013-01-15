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

// Specify file location of the file to be created
$largeFilePath = '/path/to/large/file';

// Size of file to create in bytes
$largeFileSize = 8388608;

// Create a 'large' (8MB) file
$handle = fopen($largeFilePath, 'w');
fseek($handle, $largeFileSize, SEEK_CUR);
fwrite($handle, PHP_EOL);
fclose($handle);

// Purposely set an incorrect offset
$offset = 12345;

// Upload ID to resume or null (new chunked upload)
$uploadID = null;

// Resume the upload
$chunked = null;
while (!isset($chunked['body'])) {
	$chunked = $dropbox->chunkedUpload($largeFilePath, false, '', true, $offset, $uploadID);
	if (isset($chunked['offset']) && isset($chunked['uploadID'])) {
		// The wrong offset was supplied for the resumed upload. Correct it and continue.
		$offset = $chunked['offset'];
		$uploadID = $chunked['uploadID'];
	}
}

// Dump the output
var_dump($chunked);
