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
	 * @param string $file Absolute path to the file to be uploaded
	 * @param string $path Path to upload the file to, relative to root
	 * @return object stdClass
	 */
	public function putFile($file, $path = '')
	{
		if(file_exists($file)){
			$call = 'files/' . $this->root . '/' . ltrim($path, '/');
			$params = array(
				'filename' => basename($file),
				'file' => '@' . str_replace('\\', '/', $file),
			);
			$response = $this->OAuth->fetch('POST', self::CONTENT_URL, $call, $params);
			return $response;
		}
		
		// Throw an Exception if the file does not exist
		throw new \Exception('Local file ' . $file . ' does not exist');
	}
	
	/**
	 * Downloads a file
	 * This method returns the raw file data only
	 * @param string $file Path to file, relative to root, including path
	 * @return string Contents of downloaded file
	 */
	public function getFile($file)
	{
		$call = 'files/' . $this->root . '/' . ltrim($file, '/');
		$params = array('filename' => basename($file));
		$response = $this->OAuth->fetch('GET', self::CONTENT_URL, $call, $params);
		return $response;
	}
	
	/**
	 * Retrieves file and folder metadata
	 * @return object stdClass
	 */
	public function metaData($path = null)
	{
		$call = 'metadata/' . $this->root . '/' . ltrim($path, '/');
		$response = $this->OAuth->fetch('POST', self::API_URL, $call);
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
		$call = 'shares/' . $this->root . '/' . ltrim($path, '/');
		$response = $this->OAuth->fetch('POST', self::API_URL, $call);
		return $response;
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
}
