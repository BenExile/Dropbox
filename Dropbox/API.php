<?php

/**
 * Dropbox API base class
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @link https://www.dropbox.com/developers
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
			throw new \Exception("Expected a root of either 'dropbox' or 'sandbox', got '$root'");
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
		$response = $this->OAuth->fetch('POST', self::API_URL, 'account/info');
		return $response;
	}
	
	/**
	 * Uploads a file
	 * Dropbox impose a 150MB limit to files uploaded via the API. If the file
	 * exceeds this limit or does not exist, an Exception will be thrown
	 * @param string $file Absolute path to the file to be uploaded
	 * @param string $path Path to upload the file to, relative to root
	 * @param boolean $overwrite Should the file be overwritten?
	 * @return object stdClass
	 */
	public function putFile($file, $path = '', $overwrite = true)
	{
		if(file_exists($file)){
			if(filesize($file) <= 157286400){
				$call = 'files/' . $this->root . '/' . ltrim($path, '/');
				$params = array(
					'filename' => basename($file),
					'file' => '@' . str_replace('\\', '/', $file),
					'overwrite' => (int) $overwrite,
				);
				$response = $this->OAuth->fetch('POST', self::CONTENT_URL, $call, $params);
				return $response;
			}
			throw new \Exception('File exceeds 150MB upload limit');
		}
		
		// Throw an Exception if the file does not exist
		throw new \Exception('Local file ' . $file . ' does not exist');
	}
	
	/**
	 * Downloads a file
	 * Returns the base filename, raw file data and mime type returned by Fileinfo
	 * @param string $file Path to file, relative to root, including path
	 * @return array
	 */
	public function getFile($file, $revision = null)
	{
		$call = 'files/' . $this->root . '/' . ltrim($file, '/');
		$params = array('rev' => $revision);
		$response = $this->OAuth->fetch('GET', self::CONTENT_URL, $call, $params);
		
		return array(
			'name' => basename($file),
			'mime' => $this->getMimeType($response),
			'data' => $response,
		);
	}
	
	/**
	 * Retrieves file and folder metadata
	 * @param string $path The path to the file/folder, relative to root
	 * @return object stdClass
	 */
	public function metaData($path = null)
	{
		$call = 'metadata/' . $this->root . '/' . ltrim($path, '/');
		$response = $this->OAuth->fetch('POST', self::API_URL, $call);
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
		$call = 'revisions/' . $this->root . '/' . ltrim($file, '/');
		$params = array(
			'rev_limit' => ($limit < 1) ? 1 : (($limit > 1000) ? 1000 : (int) $limit),
		);
		$response = $this->OAuth->fetch('GET', self::API_URL, $call, $params);
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
		$call = 'restore/' . $this->root . '/' . ltrim($file, '/');
		$params = array('rev' => $revision);
		$response = $this->OAuth->fetch('POST', self::API_URL, $call, $params);
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
		$call = 'search/' . $this->root . '/' . ltrim($path, '/');
		$params = array(
			'query' => $query,
			'file_limit' => ($limit < 1) ? 1 : (($limit > 1000) ? 1000 : (int) $limit),
			'include_deleted' => (int) $deleted,
		);
		$response = $this->OAuth->fetch('GET', self::API_URL, $call, $params);
		return $response;
	}
	
	/**
	 * Creates and returns a shareable link to files or folders
	 * @param string $path The path to the file/folder you want a sharable link to
	 * @return object stdClass
	 */
	public function shares($path)
	{
		$call = 'shares/' . $this->root . '/' . ltrim($path, '/');
		$response = $this->OAuth->fetch('POST', self::API_URL, $call);
		return $response;
	}
	
	/**
	 * Returns a link directly to a file
	 * @param string $path The path to the media file you want a direct link to
	 * @return object stdClass
	 */
	public function media($path)
	{
		$call = 'media/' . $this->root . '/' . ltrim($path, '/');
		$response = $this->OAuth->fetch('POST', self::API_URL, $call);
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
		$format = strtoupper($format);
		// If $format is not 'PNG', default to 'JPEG'
		if($format != 'PNG') $format = 'JPEG';
		
		$size = strtolower($size);
		$sizes = array('s', 'm', 'l', 'xl', 'small', 'medium', 'large');
		// If $size is not valid, default to 'small'
		if(!in_array($size, $sizes)) $size = 'small';
		
		// Encode the filename for use in the signature base string
		$encoded = rawurlencode(ltrim($file, '/'));
		$call = 'thumbnails/' . $this->root . '/' . $encoded;
		$params = array('format' => $format, 'size' => $size);
		$response = $this->OAuth->fetch('GET', self::CONTENT_URL, $call, $params);
		
		return array(
			'name' => basename($file),
			'mime' => $this->getMimeType($response),
			'data' => $response,
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
			'from_path' => ltrim($from, '/'),
			'to_path' => ltrim($to, '/'),
		);
		$response = $this->OAuth->fetch('POST', self::API_URL, $call, $params);
		return $response;
	}
	
	/**
	 * Creates a folder
	 * @param unknown_type New folder to create relative to root
	 * @return object stdClass
	 */
	public function create($path)
	{
		$call = 'fileops/create_folder';
		$params = array('root' => $this->root, 'path' => ltrim($path, '/'));
		$response = $this->OAuth->fetch('POST', self::API_URL, $call, $params);
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
		$params = array('root' => $this->root, 'path' => ltrim($path, '/'));
		$response = $this->OAuth->fetch('POST', self::API_URL, $call, $params);
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
				'from_path' => ltrim($from, '/'),
				'to_path' => ltrim($to, '/'),
		);
		$response = $this->OAuth->fetch('POST', self::API_URL, $call, $params);
		return $response;
	}
	
	/**
	 * Get the mime type of downloaded file
	 * If the Fileinfo extension is not loaded, return false
	 * @param string $data File contents as a string
	 * @return boolean|string Mime type and encoding of the file
	 */
	private function getMimeType($data)
	{
		if(extension_loaded('fileinfo')){
			$finfo = new \finfo(FILEINFO_MIME);
			return $finfo->buffer($data);
		}
		return false;
	}
}
