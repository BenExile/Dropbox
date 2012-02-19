<?php

/**
 * Dropbox API base class
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @link https://www.dropbox.com/developers
 * @link https://status.dropbox.com Dropbox status
 * @todo Add Dropbox Exception classes
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
	 * @var null|OAuth\Consumer 
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
	 * Set the OAuth consumer object
	 * @param OAuth\Consumer $OAuth
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
		if($root !== 'sandbox' && $root !== 'dropbox'){
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
		if(file_exists($file)){
			if(filesize($file) <= 157286400){
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
		if($this->responseFormat !== 'php'){
			throw new Exception('This method only supports the `php` response format');
		}
		
		$handle = null;
		if($outFile !== false){
			// Create a file handle if $outFile is specified
			if(!$handle = fopen($outFile, 'w')){
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
		if($handle) fclose($handle);

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
	 * Obtains metadata for the previous revisions of a file
	 * @param string Path to the file, relative to root
	 * @param integer Number of revisions to return (1-1000)
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
	 * @param string $path The path to the file/folder you want a sharable link to
	 * @return object stdClass
	 */
	public function shares($path)
	{
		$call = 'shares/' . $this->root . '/' .$this->encodePath($path);
		$response = $this->fetch('POST', self::API_URL, $call);
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
		if($this->responseFormat !== 'php'){
			throw new Exception('This method only supports the `php` response format');
		}
		
		$format = strtoupper($format);
		// If $format is not 'PNG', default to 'JPEG'
		if($format != 'PNG') $format = 'JPEG';
		
		$size = strtolower($size);
		$sizes = array('s', 'm', 'l', 'xl', 'small', 'medium', 'large');
		// If $size is not valid, default to 'small'
		if(!in_array($size, $sizes)) $size = 'small';
		
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
	 * Copies a file or folder to a new location
	 * @param string $from File or folder to be copied, relative to root
	 * @param string $to Destination path, relative to root
	 * @return object stdClass
	 */
	public function copy($from, $to)
	{
		$call = 'fileops/copy';
		$params = array(
			'root' => $this->root,
			'from_path' => $this->normalisePath($from),
			'to_path' => $this->normalisePath($to),
		);
		$response = $this->fetch('POST', self::API_URL, $call, $params);
		return $response;
	}
	
	/**
	 * Creates a folder
	 * @param string New folder to create relative to root
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
		switch($this->responseFormat){
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
		if(!in_array($format, array('php', 'json', 'jsonp'))){
			throw new Exception("Expected a format of php, json or jsonp, got '$format'");
		} else {
			$this->responseFormat = $format;
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
		if(extension_loaded('fileinfo')){
			$finfo = new \finfo(FILEINFO_MIME);
			if($isFilename !== false) return $finfo->file($data);
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
