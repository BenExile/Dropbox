<?php

/**
 * Dropbox API base class
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @link https://www.dropbox.com/developers
 * @link https://status.dropbox.com Dropbox status
 * @package Dropbox
 */
namespace Dropbox;

class API
{
    // API Endpoints
    const API_URL     = 'https://api.dropbox.com/1/';
    const CONTENT_URL = 'https://api-content.dropbox.com/1/';
    
    /**
     * OAuth consumer object
     * @var null|OAuth\Consumer\ConsumerAbstract
     */
    private $OAuth;
    
    /**
     * The root level for file paths
     * Either `dropbox` or `sandbox` (preferred)
     * @var null|string
     */
    private $root;
    
    /**
     * Format of the API response
     * @var string
     */
    private $responseFormat = 'php';
    
    /**
     * JSONP callback
     * @var string
     */
    private $callback = 'dropboxCallback';
    
    /**
     * Chunk size used for chunked uploads
     * @see \Dropbox\API::chunkedUpload()
     */
    private $chunkSize = 4194304;
    
    /**
     * Set the OAuth consumer object
     * See 'General Notes' at the link below for information on access type
     * @link https://www.dropbox.com/developers/reference/api
     * @param OAuth\Consumer\ConsumerAbstract $OAuth
     * @param string $root Dropbox app access type
     */
    public function __construct(OAuth\Consumer\ConsumerAbstract $OAuth, $root = 'sandbox')
    {
        $this->OAuth = $OAuth;
        $this->setRoot($root);
    }
    
    /**
    * Set the root level
    * @param mixed $root
    * @throws Exception
    * @return void
    */
    public function setRoot($root)
    {
        if ($root !== 'sandbox' && $root !== 'dropbox') {
            throw new Exception("Expected a root of either 'dropbox' or 'sandbox', got '$root'");
        } else {
            $this->root = $root;
        }
    }
    
    /**
     * Retrieves information about the user's account
     * @return object stdClass
     */
    public function accountInfo()
    {
        $response = $this->fetch('POST', self::API_URL, 'account/info');
        return $response;
    }
    
    /**
     * Uploads a physical file from disk
     * Dropbox impose a 150MB limit to files uploaded via the API. If the file
     * exceeds this limit or does not exist, an Exception will be thrown
     * @param string $file Absolute path to the file to be uploaded
     * @param string|bool $filename The destination filename of the uploaded file
     * @param string $path Path to upload the file to, relative to root
     * @param boolean $overwrite Should the file be overwritten? (Default: true)
     * @return object stdClass
     */
    public function putFile($file, $filename = false, $path = '', $overwrite = true)
    {
        if (file_exists($file)) {
            if (filesize($file) <= 157286400) {
                $call = 'files/' . $this->root . '/' . $this->encodePath($path);
                // If no filename is provided we'll use the original filename
                $filename = (is_string($filename)) ? $filename : basename($file);
                $params = array(
                    'filename' => $filename,
                    'file' => '@' . str_replace('\\', '/', $file) . ';filename=' . $filename,
                    'overwrite' => (int) $overwrite,
                );
                $response = $this->fetch('POST', self::CONTENT_URL, $call, $params);
                return $response;
            }
            throw new Exception('File exceeds 150MB upload limit');
        }
        
        // Throw an Exception if the file does not exist
        throw new Exception('Local file ' . $file . ' does not exist');
    }
    
    /**
     * Uploads file data from a stream
     * Note: This function is experimental and requires further testing
     * @todo Add filesize check
     * @param resource $stream A readable stream created using fopen()
     * @param string $filename The destination filename, including path
     * @param boolean $overwrite Should the file be overwritten? (Default: true)
     * @return array
     */
    public function putStream($stream, $filename, $overwrite = true)
    {
        $this->OAuth->setInFile($stream);
        $path = $this->encodePath($filename);
        $call = 'files_put/' . $this->root . '/' . $path;
        $params = array('overwrite' => (int) $overwrite);
        $response = $this->fetch('PUT', self::CONTENT_URL, $call, $params);
        return $response;
    }
    
    /**
     * Uploads large files to Dropbox in mulitple chunks
     * @param string $file Absolute path to the file to be uploaded
     * @param string|bool $filename The destination filename of the uploaded file
     * @param string $path Path to upload the file to, relative to root
     * @param boolean $overwrite Should the file be overwritten? (Default: true)
     * @return object
     */
    public function chunkedUpload($file, $filename = false, $path = '', $overwrite = true)
    {
        if (!file_exists($file)) {
            throw new Exception('Local file ' . $file . ' does not exist');
        }
        if (!$handle = @fopen($file, 'r')) {
            throw new Exception('Could not open ' . $file . ' for reading');
        }

        $data = $this->readFully($handle, $this->chunkSize);
        $len = strlen($data);

        $client = $this;
        $uploadId = $this->runWithRetry(3, function() use ($data, $client) {
            return $client->chunkedUploadStart($data);
        });

        $byteOffset = $len;

        while (!feof($handle)) {
            $data = $this->readFully($handle, $this->chunkSize);
            $len = strlen($data);

            while (true) {
                $r = $this->runWithRetry(3,
                    function() use ($client, $uploadId, $byteOffset, $data) {
                        return $client->chunkedUploadContinue($uploadId, $byteOffset, $data);
                    });

                if ($r === true) {  // Chunk got uploaded!
                    $byteOffset += $len;
                    break;
                }
                if ($r === false) {  // Server didn't recognize our upload ID
                    // This is very unlikely since we're uploading all the chunks in sequence.
                    throw new Exception\BadResponseException("Server forgot our uploadId");
                }

                // Otherwise, the server is at a different byte offset from us.
                $serverByteOffset = $r;
                // An earlier byte offset means the server has lost data we sent earlier.
                if ($serverByteOffset < $byteOffset) throw new Exception\BadResponseException(
                    "Server is at an ealier byte offset: us=$byteOffset, server=$serverByteOffset");
                $diff = $serverByteOffset - $byteOffset;
                // If the server is past where we think it could possibly be, something went wrong.
                if ($diff > $len) throw new Exception\BadResponseException(
                    "Server is more than a chunk ahead: us=$byteOffset, server=$serverByteOffset");
                // The normal case is that the server is a bit further along than us because of a
                // partially-uploaded chunk.  Finish it off.
                $byteOffset += $diff;
                if ($diff === $len) break;  // If the server is at the end, we're done.
                $data = substr($data, $diff);
            }
        }

        $metadata = $this->runWithRetry(3,
            function() use ($client, $uploadId, $file, $filename, $path, $overwrite) {
                $filename = (is_string($filename)) ? $filename : basename($file);
                $filePath = rtrim($path, '/') . '/' . $filename;

                return $client->chunkedUploadFinish($uploadId, $filePath, $overwrite);
            });
        return $metadata;
    }

    /**
     * Sometimes fread() returns less than the request number of bytes (for example, when reading
     * from network streams).  This function repeatedly calls fread until the requested number of
     * bytes have been read or we've reached EOF.
     *
     * @param resource $inStream
     * @param int $numBytes
     * @return string
     */
    private function readFully($inStream, $numBytes)
    {
        $full = '';
        $bytesRemaining = $numBytes;
        while (!feof($inStream) && $bytesRemaining > 0) {
            $part = fread($inStream, $bytesRemaining);
            if ($part === false) throw new Exception("Error reading from \$inStream.");
            $full .= $part;
            $bytesRemaining -= strlen($part);
        }
        return $full;
    }

    private function runWithRetry($maxRetries, $action)
    {
        $retryDelay = 1;
        $numRetries = 0;
        while (true) {
            try {
                return $action();
            } catch (Exception $ex) {
                $savedEx = $ex;
            }

            // We maxed out our retries.  Propagate the last exception we got.
            if ($numRetries >= $maxRetries) throw $savedEx;

            $numRetries++;
            sleep($retryDelay);
            $retryDelay *= 2;  // Exponential back-off.
        }
        throw new \RuntimeException("unreachable");
    }

    /**
     * Start a new chunked upload session and upload the first chunk of data.
     *
     * @param string $data
     *     The data to start off the chunked upload session.
     *
     * @return array
     *     A pair of <code>(string $uploadId, int $byteOffset)</code>.  <code>$uploadId</code>
     *     is a unique identifier for this chunked upload session.  You pass this in to
     *     {@link chunkedUploadContinue} and {@link chuunkedUploadFinish}.  <code>$byteOffset</code>
     *     is the number of bytes that were successfully uploaded.
     */
    private function chunkedUploadStart($data)
    {
        $response = $this->fetch('PUT', self::CONTENT_URL, 'chunked_upload', array(
            'putdata' => $data,
        ));

        if ($response['code'] === 404) {
            throw new Exception\BadResponseException("Got a 404, but we didn't send up an 'upload_id'");
        }

        $correction = self::_chunkedUploadCheckForOffsetCorrection($response);
        if ($correction !== null) throw new Exception\BadResponseException(
            "Got an offset-correcting 400 response, but we didn't send an offset");

        if ($response['code'] !== 200) throw $this->unexpectedStatus($response);

        list($uploadId, $byteOffset) = self::_chunkedUploadParse200Response($response['body']);
        $len = strlen($data);
        if ($byteOffset !== $len) throw new Exception\BadResponseException(
            "We sent $len bytes, but server returned an offset of $byteOffset");

        return $uploadId;
    }

    /**
     * Append another chunk data to a previously-started chunked upload session.
     *
     * @param string $uploadId
     *     The unique identifier for the chunked upload session.  This is obtained via
     *     {@link chunkedUploadStart}.
     *
     * @param int $byteOffset
     *     The number of bytes you think you've already uploaded to the given chunked upload
     *     session.  The server will append the new chunk of data after that point.
     *
     * @param string $data
     *     The data to append to the existing chunked upload session.
     *
     * @return int|bool
     *     If <code>false</code>, it means the server didn't know about the given
     *     <code>$uploadId</code>.  This may be because the chunked upload session has expired
     *     (they last around 24 hours).
     *     If <code>true</code>, the chunk was successfully uploaded.  If an integer, it means
     *     you and the server don't agree on the current <code>$byteOffset</code>.  The returned
     *     integer is the server's internal byte offset for the chunked upload session.  You need
     *     to adjust your input to match.
     */
    function chunkedUploadContinue($uploadId, $byteOffset, $data)
    {
        $response = $this->fetch('PUT', self::CONTENT_URL, 'chunked_upload', array(
            'upload_id' => $uploadId,
            'offset' => $byteOffset,
            'putdata' => $data,
        ));

        if ($response['code'] === 404) {
            // The server doesn't know our upload ID.  Maybe it expired?
            return false;
        }

        $correction = self::_chunkedUploadCheckForOffsetCorrection($response);
        if ($correction !== null) {
            list($correctedUploadId, $correctedByteOffset) = $correction;
            if ($correctedUploadId !== $uploadId) throw new Exception\BadResponseException(
                "Corrective 400 upload_id mismatch: us=" .
                var_export($uploadId, true) . " server=" . var_export($correctedUploadId, true));
            if ($correctedByteOffset === $byteOffset) throw new Exception\BadResponseException(
                "Corrective 400 offset is the same as ours: $byteOffset");
            return $correctedByteOffset;
        }

        if ($response['code'] !== 200) throw $this->unexpectedStatus($response);
        list($retUploadId, $retByteOffset) = self::_chunkedUploadParse200Response($response['body']);

        $nextByteOffset = $byteOffset + strlen($data);
        if ($uploadId !== $retUploadId) throw new Exception\BadResponseException(
            "upload_id mismatch: us=" . var_export($uploadId, true) . ", server=" . var_export($uploadId, true));
        if ($nextByteOffset !== $retByteOffset) throw new Exception\BadResponseException(
            "next-offset mismatch: us=$nextByteOffset, server=$retByteOffset");

        return true;
    }

    /**
     * @param string $body
     * @return array
     */
    private static function _chunkedUploadParse200Response($body)
    {
        if (!isset($body->upload_id)) {
            throw new Exception\BadResponseException("missing field \"upload_id\" in " . var_export($body, true));
        }
        $uploadId = $body->upload_id;

        if (!isset($body->offset)) {
            throw new Exception\BadResponseException("missing field \"offset\" in " . var_export($body, true));
        }
        $byteOffset = $body->offset;

        return array($uploadId, $byteOffset);
    }

    /**
     * @param object $response
     * @return array|null
     */
    private static function _chunkedUploadCheckForOffsetCorrection($response)
    {
        if ($response['code'] !== 400) return null;
        if ($response['body'] === null) return null;
        if (!isset($response['body']->upload_id) || !isset($response['body']->offset)) return null;
        $uploadId = $response['body']->upload_id;
        $byteOffset = $response['body']->offset;
        return array($uploadId, $byteOffset);
    }

    /**
     * Creates a file on Dropbox using the accumulated contents of the given chunked upload session.
     *
     * See <a href="https://www.dropbox.com/developers/core/docs#commit-chunked-upload">/commit_chunked_upload</a>.
     *
     * @param string $uploadId
     *     The unique identifier for the chunked upload session.  This is obtained via
     *     {@link chunkedUploadStart}.
     *
     * @param string $path
     *    The Dropbox path to save the file to ($path).
     *
     * @param int $overwrite
     *    What to do if there's already a file at the given path.
     *
     * @return array|null
     *    If <code>null</code>, it means the Dropbox server wasn't aware of the
     *    <code>$uploadId</code> you gave it.
     *    Otherwise, you get back the
     *    <a href="https://www.dropbox.com/developers/core/docs#metadata-details">metadata object</a>
     *    for the newly-created file.
     *
     * @throws Exception
     */
    private function chunkedUploadFinish($uploadId, $path, $overwrite)
    {
        $call = 'commit_chunked_upload/' . $this->root . '/' . $this->encodePath($path);
        $response = $this->fetch('POST', self::CONTENT_URL, $call, array(
            'upload_id' => $uploadId,
            'overwrite' => (int) $overwrite,
        ));

        if ($response['code'] === 404) return null;
        if ($response['code'] !== 200) throw $this->unexpectedStatus($response);

        return $response;
    }

    private function unexpectedStatus($httpResponse)
    {
        $sc = $httpResponse['code'];

        $message = "HTTP status $sc";
        if (is_string($httpResponse['body'])) {
            // TODO: Maybe only include the first ~200 chars of the body?
            $message .= "\n".$httpResponse['body'];
        }

        if ($sc === 400) return new Exception\BadRequestException($message);
        if ($sc === 401) return new Exception("InvalidAccessToken $message");
        if ($sc === 500 || $sc === 502) return new Exception("ServerError $message");
        if ($sc === 503) return new Exception("RetryLater $message");

        return new Exception\BadResponseException("Unexpected $message", $sc);
    }

    /**
     * Downloads a file
     * Returns the base filename, raw file data and mime type returned by Fileinfo
     * @param string $file Path to file, relative to root, including path
     * @param string $outFile Filename to write the downloaded file to
     * @param string $revision The revision of the file to retrieve
     * @return array
     */
    public function getFile($file, $outFile = false, $revision = null)
    {
        // Only allow php response format for this call
        if ($this->responseFormat !== 'php') {
            throw new Exception('This method only supports the `php` response format');
        }
        
        $handle = null;
        if ($outFile !== false) {
            // Create a file handle if $outFile is specified
            if (!$handle = fopen($outFile, 'w')) {
                throw new Exception("Unable to open file handle for $outFile");
            } else {
                $this->OAuth->setOutFile($handle);
            }
        }
        
        $file = $this->encodePath($file);
        $call = 'files/' . $this->root . '/' . $file;
        $params = array('rev' => $revision);
        $response = $this->fetch('GET', self::CONTENT_URL, $call, $params);
        
        // Close the file handle if one was opened
        if ($handle) fclose($handle);

        return array(
            'name' => ($outFile) ? $outFile : basename($file),
            'mime' => $this->getMimeType(($outFile) ?: $response['body'], $outFile),
            'meta' => json_decode($response['headers']['x-dropbox-metadata']),
            'data' => $response['body'],
        );
    }
    
    /**
     * Retrieves file and folder metadata
     * @param string $path The path to the file/folder, relative to root
     * @param string $rev Return metadata for a specific revision (Default: latest rev)
     * @param int $limit Maximum number of listings to return
     * @param string $hash Metadata hash to compare against
     * @param bool $list Return contents field with response
     * @param bool $deleted Include files/folders that have been deleted
     * @return object stdClass 
     */
    public function metaData($path = null, $rev = null, $limit = 10000, $hash = false, $list = true, $deleted = false)
    {
        $call = 'metadata/' . $this->root . '/' . $this->encodePath($path);
        $params = array(
            'file_limit' => ($limit < 1) ? 1 : (($limit > 10000) ? 10000 : (int) $limit),
            'hash' => (is_string($hash)) ? $hash : 0,
            'list' => (int) $list,
            'include_deleted' => (int) $deleted,
            'rev' => (is_string($rev)) ? $rev : null,
        );
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Return "delta entries", intructing you how to update
     * your application state to match the server's state
     * Important: This method does not make changes to the application state
     * @param null|string $cursor Used to keep track of your current state
     * @return array Array of delta entries
     */
    public function delta($cursor = null)
    {
        $call = 'delta';
        $params = array('cursor' => $cursor);
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Obtains metadata for the previous revisions of a file
     * @param string $file Path to the file, relative to root
     * @param integer $limit Number of revisions to return (1-1000)
     * @return array
     */
    public function revisions($file, $limit = 10)
    {
        $call = 'revisions/' . $this->root . '/' . $this->encodePath($file);
        $params = array(
            'rev_limit' => ($limit < 1) ? 1 : (($limit > 1000) ? 1000 : (int) $limit),
        );
        $response = $this->fetch('GET', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Restores a file path to a previous revision
     * @param string $file Path to the file, relative to root
     * @param string $revision The revision of the file to restore
     * @return object stdClass
     */
    public function restore($file, $revision)
    {
        $call = 'restore/' . $this->root . '/' . $this->encodePath($file);
        $params = array('rev' => $revision);
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Returns metadata for all files and folders that match the search query
     * @param mixed $query The search string. Must be at least 3 characters long
     * @param string $path The path to the folder you want to search in
     * @param integer $limit Maximum number of results to return (1-1000)
     * @param boolean $deleted Include deleted files/folders in the search
     * @return array
     */
    public function search($query, $path = '', $limit = 1000, $deleted = false)
    {
        $call = 'search/' . $this->root . '/' . $this->encodePath($path);
        $params = array(
            'query' => $query,
            'file_limit' => ($limit < 1) ? 1 : (($limit > 1000) ? 1000 : (int) $limit),
            'include_deleted' => (int) $deleted,
        );
        $response = $this->fetch('GET', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Creates and returns a shareable link to files or folders
     * The link returned is for a preview page from which the user an choose to
     * download the file if they wish. For direct download links, see media().
     * @param string $path The path to the file/folder you want a sharable link to
     * @return object stdClass
     */
    public function shares($path, $shortUrl = true)
    {
        $call = 'shares/' . $this->root . '/' .$this->encodePath($path);
        $params = array('short_url' => ($shortUrl) ? 1 : 0);
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Returns a link directly to a file
     * @param string $path The path to the media file you want a direct link to
     * @return object stdClass
     */
    public function media($path)
    {
        $call = 'media/' . $this->root . '/' . $this->encodePath($path);
        $response = $this->fetch('POST', self::API_URL, $call);
        return $response;
    }
    
    /**
     * Gets a thumbnail for an image
     * @param string $file The path to the image you wish to thumbnail
     * @param string $format The thumbnail format, either JPEG or PNG
     * @param string $size The size of the thumbnail
     * @return array
     */
    public function thumbnails($file, $format = 'JPEG', $size = 'small')
    {
        // Only allow php response format for this call
        if ($this->responseFormat !== 'php') {
            throw new Exception('This method only supports the `php` response format');
        }
        
        $format = strtoupper($format);
        // If $format is not 'PNG', default to 'JPEG'
        if ($format != 'PNG') $format = 'JPEG';
        
        $size = strtolower($size);
        $sizes = array('s', 'm', 'l', 'xl', 'small', 'medium', 'large');
        // If $size is not valid, default to 'small'
        if (!in_array($size, $sizes)) $size = 'small';
        
        $call = 'thumbnails/' . $this->root . '/' . $this->encodePath($file);
        $params = array('format' => $format, 'size' => $size);
        $response = $this->fetch('GET', self::CONTENT_URL, $call, $params);
        
        return array(
            'name' => basename($file),
            'mime' => $this->getMimeType($response['body']),
            'meta' => json_decode($response['headers']['x-dropbox-metadata']),
            'data' => $response['body'],
        );
    }
    
    /**
     * Creates and returns a copy_ref to a file
     * This reference string can be used to copy that file to another user's
     * Dropbox by passing it in as the from_copy_ref parameter on /fileops/copy
     * @param string $path File for which ref should be created, relative to root
     * @return array
     */
    public function copyRef($path)
    {
        $call = 'copy_ref/' . $this->root . '/' . $this->encodePath($path);
        $response = $this->fetch('GET', self::API_URL, $call);
        return $response;
    }
    
    /**
     * Copies a file or folder to a new location
     * @param string $from File or folder to be copied, relative to root
     * @param string $to Destination path, relative to root
     * @param null|string $fromCopyRef Must be used instead of the from_path
     * @return object stdClass
     */
    public function copy($from, $to, $fromCopyRef = null)
    {
        $call = 'fileops/copy';
        $params = array(
            'root' => $this->root,
            'from_path' => $this->normalisePath($from),
            'to_path' => $this->normalisePath($to),
        );
        
        if ($fromCopyRef) {
            $params['from_path'] = null;
            $params['from_copy_ref'] = $fromCopyRef;
        }
        
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Creates a folder
     * @param string $path New folder to create relative to root
     * @return object stdClass
     */
    public function create($path)
    {
        $call = 'fileops/create_folder';
        $params = array('root' => $this->root, 'path' => $this->normalisePath($path));
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Deletes a file or folder
     * @param string $path The path to the file or folder to be deleted
     * @return object stdClass
     */
    public function delete($path)
    {
        $call = 'fileops/delete';
        $params = array('root' => $this->root, 'path' => $this->normalisePath($path));
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Moves a file or folder to a new location
     * @param string $from File or folder to be moved, relative to root
     * @param string $to Destination path, relative to root
     * @return object stdClass
     */
    public function move($from, $to)
    {
        $call = 'fileops/move';
        $params = array(
                'root' => $this->root,
                'from_path' => $this->normalisePath($from),
                'to_path' => $this->normalisePath($to),
        );
        $response = $this->fetch('POST', self::API_URL, $call, $params);
        return $response;
    }
    
    /**
     * Intermediate fetch function
     * @param string $method The HTTP method
     * @param string $url The API endpoint
     * @param string $call The API method to call
     * @param array $params Additional parameters
     * @return mixed
     */
    private function fetch($method, $url, $call, array $params = array())
    {
        // Make the API call via the consumer
        $response = $this->OAuth->fetch($method, $url, $call, $params);
        
        // Format the response and return
        switch ($this->responseFormat) {
            case 'json':
                return json_encode($response);
            case 'jsonp':
                $response = json_encode($response);
                return $this->callback . '(' . $response . ')';
            default:
                return $response;
        }
    }
    
    /**
     * Set the API response format
     * @param string $format One of php, json or jsonp
     * @return void
     */
    public function setResponseFormat($format)
    {
        $format = strtolower($format);
        if (!in_array($format, array('php', 'json', 'jsonp'))) {
            throw new Exception("Expected a format of php, json or jsonp, got '$format'");
        } else {
            $this->responseFormat = $format;
        }
    }
    
    /**
     * Set the chunk size for chunked uploads
     * If $chunkSize is empty, set to 4194304 bytes (4 MB)
     * @see \Dropbox\API\chunkedUpload()
     */
    public function setChunkSize($chunkSize = 4194304)
    {
        if (!is_int($chunkSize)) {
            throw new Exception('Expecting chunk size to be an integer, got ' . gettype($chunkSize));
        } elseif ($chunkSize > 157286400) {
            throw new Exception('Chunk size must not exceed 157286400 bytes, got ' . $chunkSize);
        } else {
            $this->chunkSize = $chunkSize;
        }
    }
    
    /**
    * Set the JSONP callback function
    * @param string $function
    * @return void
    */
    public function setCallback($function)
    {
        $this->callback = $function;
    }
    
    /**
     * Get the mime type of downloaded file
     * If the Fileinfo extension is not loaded, return false
     * @param string $data File contents as a string or filename
     * @param string $isFilename Is $data a filename?
     * @return boolean|string Mime type and encoding of the file
     */
    private function getMimeType($data, $isFilename = false)
    {
        if (extension_loaded('fileinfo')) {
            $finfo = new \finfo(FILEINFO_MIME);
            if ($isFilename !== false) {
                return $finfo->file($data);
            }
            return $finfo->buffer($data);
        }
        return false;
    }
    
    /**
     * Trim the path of forward slashes and replace
     * consecutive forward slashes with a single slash
     * @param string $path The path to normalise
     * @return string
     */
    private function normalisePath($path)
    {
        $path = preg_replace('#/+#', '/', trim($path, '/'));
        return $path;
    }
    
    /**
     * Encode the path, then replace encoded slashes
     * with literal forward slash characters
     * @param string $path The path to encode
     * @return string
     */
    private function encodePath($path)
    {
        $path = $this->normalisePath($path);
        $path = str_replace('%2F', '/', rawurlencode($path));
        return $path;
    }
}
