<?php

/**
 * Retreive the metadata for a file/folder
 * @link https://www.dropbox.com/developers/reference/api#metadata
 * @link https://github.com/BenTheDesigner/Dropbox/blob/master/Dropbox/API.php#L170-192
 */

// Require the bootstrap
require_once('bootstrap.php');

// Set the file path
$path = '';

// Note: Use a hash to test the NotModifiedException
$hash = null;

// Limit the entries returned
$limit = 10000;

try {
    // Get the metadata for the file/folder specified in $path
    $metaData = $dropbox->metaData($path, null, $limit, $hash);
    var_dump($metaData);
} catch (\Dropbox\Exception\NotModifiedException $e) {
    // The contents were not changed since $hash was issued
    // As you *should* be caching metadata responses, you can revert to using cached data
    echo 'The folder contents have not been modified';
} catch (\Dropbox\Exception\NotAcceptableException $e) {
    // The number of entries exceeds $limit
    echo 'Too many entries to return';
}
