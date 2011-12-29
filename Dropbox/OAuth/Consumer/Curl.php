<?php

/**
* OAuth consumer using PHP cURL
* @author Ben Tadiar <ben@handcraftedbyben.co.uk>
* @link https://github.com/benthedesigner/dropbox
* @package Dropbox\OAuth
* @subpackage Consumer
*/
namespace Dropbox\OAuth\Consumer;
use Dropbox\API as API;
use Dropbox\OAuth\Storage\StorageInterface as StorageInterface;

class Curl extends ConsumerAbstract
{	
	/**
	 * Default cURL options
	 * @var array
	 */
	private $options = array(
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_VERBOSE        => true,
		CURLOPT_HEADER         => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
	);
	
	/**
	 * Set properties and begin authentication
	 * @param string $key
	 * @param string $secret
	 * @param StorageInterface $storage
	 * @param string $callback
	 */
	public function __construct($key, $secret, StorageInterface $storage, $callback)
	{
		// Check the cURL extension is loaded
		if(!extension_loaded('curl')){
			throw new \Exception('The cURL OAuth consumer requires the cURL extension');
		}
		
		$this->consumerKey = $key;
		$this->consumerSecret = $secret;
		$this->storage = $storage;
		$this->callback = $callback;
		$this->authenticate();
	}
	
	/**
	 * Acquire an unauthorised request token
	 * @link http://tools.ietf.org/html/rfc5849#section-2.1
	 * @return void
	 */
	protected function getRequestToken()
	{
		$url = API::API_URL . self::REQUEST_TOKEN_METHOD;
		$response = $this->fetch('POST', $url, '');
		$token = $this->parseTokenString($response);
		$this->storage->set($token);
	}
	
	/**
	 * Obtain user authorisation
	 * The user will be redirected to Dropbox' web endpoint
	 * @link http://tools.ietf.org/html/rfc5849#section-2.2
	 * @return void
	 */
	protected function authorise($callbackUrl = null)
	{
		// Get the request token
		$token = $this->getToken();
		
		// Prepare request parameters
		$params = array(
			'oauth_token' => $token->oauth_token,
			'oauth_token_secret' => $token->oauth_token_secret,
			'oauth_callback' => $callbackUrl,
		);
		
		// Build the URL and redirect the user
		$query = '?' . http_build_query($params, '', '&');
		$url = self::WEB_URL . self::AUTHORISE_METHOD . $query;
		header('Location: ' . $url);
		exit;
	}
	
	/**
	 * Acquire an access token
	 * Tokens acquired at this point should be stored to
	 * prevent having to request new tokens for each API call
	 * @link http://tools.ietf.org/html/rfc5849#section-2.3
	 */
	protected function getAccessToken()
	{
		// Get the signed request URL
		$response = $this->fetch('POST', API::API_URL, self::ACCESS_TOKEN_METHOD);
		$token = $this->parseTokenString($response);
		$this->storage->set($token);
	}

	/**
	 * Execute an API call
	 * @todo Improve error handling
	 * @param string $method The HTTP method
	 * @param string $url The API endpoint
	 * @param string $call The API method to call
	 * @param array $params Additional parameters
	 * @return string|object stdClass
	 */
	public function fetch($method, $url, $call = '', array $additional = array())
	{
		// Get the signed request URL
		$request = $this->getSignedRequest($method, $url, $call, $additional);
		
		// Initialise and execute a cURL request
		$handle = curl_init($request['url']);
		curl_setopt_array($handle, $this->options);
		
		// POST request specific
		if($method == 'POST'){
			curl_setopt($handle, CURLOPT_POST, true);
			curl_setopt($handle, CURLOPT_POSTFIELDS, $request['postfields']);
		}
		
		// Execute and parse the response
		$raw = curl_exec($handle);
		var_dump($raw);
		curl_close($handle);
		$response = $this->parse($raw);
		
		// Check if an error occurred and throw an Exception
		if(!empty($response['body']->error)){
			$message = $response['body']->error . ' (Status Code: ' . $response['code'] . ')';
			throw new \Exception($message);
		}
		
		return $response['body'];
	}
	
	/**
	 * Parse a cURL response
	 * @param string $response 
	 * @return array
	 */
	private function parse($response)
	{
		// Explode the response into headers and body parts (separated by double EOL)
		list($headers, $response) = explode("\r\n\r\n", $response, 2);
		
		// Explode response headers
		$lines = explode("\r\n", $headers);
		
		// If the status code is 100, the API server must send a final response
		// We need to explode the response again to get the actual response
		if(preg_match('#^HTTP/1.1 100#', $lines[0])){
			list($headers, $response) = explode("\r\n\r\n", $response, 2);
			$lines = explode("\r\n", $headers);
		}
		
		// Get the HTTP response code from the first line
		$first = array_shift($lines);
		$pattern = '#^HTTP/1.1 ([0-9]{3})#';
		preg_match($pattern, $first, $matches);
		$code = $matches[1];
        
		// Parse the remaining headers into an associative array
		// Note: Headers are not returned at present, but may be useful
		$headers = array();
		foreach ($lines as $line){
			list($k, $v) = explode(': ', $line, 2);
			$headers[strtolower($k)] = $v;
		}
		
		// If the response body is not a JSON encoded string
		// we'll return the entire response body
		if(!$body = json_decode($response)){
			$body = $response;
		}
		
		return array('code' => $code, 'body' => $body);
	}
	
	/**
	 * Parse response parameters for a token into an object
	 * Dropbox returns tokens in the response parameters, and
	 * not a JSON encoded object as per other API requests
	 * @link http://oauth.net/core/1.0/#response_parameters
	 * @param string $response
	 * @return object stdClass
	 */
	private function parseTokenString($response)
	{
		$parts = explode('&', $response);
		$token = new \stdClass();
		foreach($parts as $part){
			list($k, $v) = explode('=', $part, 2);
			$k = strtolower($k);
			$token->$k = $v;
		}
		return $token;
	}
}
