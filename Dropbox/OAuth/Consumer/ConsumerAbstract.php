<?php

/**
* Abstract OAuth consumer
* @author Ben Tadiar <ben@handcraftedbyben.co.uk>
* @link https://github.com/benthedesigner/dropbox
* @package Dropbox\OAuth
* @subpackage Consumer
*/
namespace Dropbox\OAuth\Consumer;
abstract class ConsumerAbstract
{
	// Dropbox web endpoint
	const WEB_URL = 'https://www.dropbox.com/1/';
	
	// OAuth flow methods
	const REQUEST_TOKEN_METHOD = 'oauth/request_token';
	const AUTHORISE_METHOD = 'oauth/authorize';
	const ACCESS_TOKEN_METHOD = 'oauth/access_token';
	
	/**
	 * Signature method, either PLAINTEXT or HMAC-SHA1
	 * @var string
	 */
	private $sigMethod = 'HMAC-SHA1';
	
	/**
	 * Authenticate using 3-legged OAuth flow, firstly
	 * checking we don't already have tokens to use
	 * @todo This needs work...
	 */
	protected function authenticate()
	{
		if((!$token = $this->storage->get()) || !isset($token->uid)){
			if(!isset($_GET['uid'], $_GET['oauth_token'])){
				$this->storage->set(null);
				$this->getRequestToken();
				$this->authorise($this->callback);
			} else {
				$this->getAccessToken();
			}
		}
	}
	
	/**
	 * Get the request/access token
	 * This will return the access/request token depending on
	 * which stage we are at in the OAuth flow, or a dummy object
	 * if we have not yet started the authentication process
	 * @return object stdClass
	 */
	protected function getToken()
	{
		if(!$token = $this->storage->get()){
			$token = new \stdClass();
			$token->oauth_token = null;
			$token->oauth_token_secret = null;
		}
		return $token;
	}
	
	/**
	 * Generate signed request URL
	 * See inline comments for description
	 * @link http://tools.ietf.org/html/rfc5849#section-3.4
	 */
	protected function getSignedRequest($method, $url, $call, array $additional = array())
	{
		// Get the request/access token
		$token = $this->getToken();
		
		// Generate a random string for the request
		$nonce = md5(microtime(true) . uniqid('', true));
		
		// Prepare the standard request parameters
		$params = array(
			'oauth_consumer_key' => $this->consumerKey,
			'oauth_token' => $token->oauth_token,
			'oauth_signature_method' => $this->sigMethod,
			'oauth_version' => '1.0',
			// Generate nonce and timestamp if signature method is HMAC-SHA1 
			'oauth_timestamp' => ($this->sigMethod == 'HMAC-SHA1') ? time() : null,
			'oauth_nonce' => ($this->sigMethod == 'HMAC-SHA1') ? $nonce : null,
		);
	
		// Merge with the additional request parameters
		$params = array_merge($params, $additional);
		ksort($params);
	
		// URL encode each parameter to RFC3986 for use in the base string
		$encoded = array();
		foreach($params as $param => $value){
			if($value !== null){
				if($value[0] === '@') $value = $params['filename'];
				$encoded[] = $this->encode($param) . '=' . $this->encode($value);
			} else {
				unset($params[$param]);
			}
		}
		
		// Build the first part of the string
		$base = $method . '&' . $this->encode($url . $call) . '&';
		
		// Re-encode the encoded parameter string and append to $base
		$base .= $this->encode(implode('&', $encoded));
		echo $base;
		// Concatenate the secrets with an ampersand
		$key = $this->consumerSecret . '&' . $token->oauth_token_secret;
		
		// Get the signature string based on signature method
		$signature = $this->getSignature($base, $key);
		$params['oauth_signature'] = $signature;
		
		// Build the signed request URL
		$query = '?' . http_build_query($params);
		return array(
			'url' => $url . $call . $query,
			'postfields' => $params,
		);
	}
	
	/**
	 * Generate the oauth_signature for a request
	 * @param string $base Signature base string, used by HMAC-SHA1
	 * @param string $key Concatenated consumer and token secrets
	 */
	private function getSignature($base, $key)
	{
		switch($this->sigMethod){
			case 'PLAINTEXT':
				$signature = $key;
				break;
			case 'HMAC-SHA1':
				$signature = base64_encode(hash_hmac('sha1', $base, $key, true));
				break;
		}
		
		return $signature;
	}
	
	/**
	 * Set the OAuth signature method
	 * @param string $method Either PLAINTEXT or HMAC-SHA1
	 * @return void
	 */
	public function setSignatureMethod($method)
	{
		$method = strtoupper($method);
		switch($method){
			case 'PLAINTEXT':
			case 'HMAC-SHA1':
				$this->sigMethod = $method;
				break;
			default:
				throw new \Exception('Unsupported signature method ' . $method);
		}
	}
	
	/**
	 * Encode a value to RFC3986
	 * This is a convenience method to decode ~ symbols encoded
	 * by rawurldecode. This will encode all characters except
	 * the unreserved set, ALPHA, DIGIT, '-', '.', '_', '~'
	 * @link http://tools.ietf.org/html/rfc5849#section-3.6
	 * @param mixed $value
	 */
	private function encode($value)
	{
		return str_replace('%7E', '~', rawurlencode($value));
	}
}
