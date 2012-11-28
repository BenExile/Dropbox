<?php

/**
 * Download a file and its metadata
 * The object returned will contain the file name, MIME type, metadata 
 * (obtained from x-dropbox-metadata HTTP header) and file contents
 * @link https://www.dropbox.com/developers/reference/api#files-GET
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L129-168
 */

// Require the bootstrap
require_once('bootstrap.php');

// Set the file path
// You will need to modify $path or run putFile.php first
$path = 'api_upload_test.txt';

// Set the output file
// If $outFile is set, the downloaded file will be written
// directly to disk rather than storing file data in memory
$outFile = false;

// Download the file
$file = $dropbox->getFile($path, $outFile);

// Dump the output
var_dump($file);
