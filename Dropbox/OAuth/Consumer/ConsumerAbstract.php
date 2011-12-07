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
	 * Authenticate using 3-legged OAuth flow, firstly
	 * checking we don't already have tokens to use
	 * @todo This needs work...
	 */
	protected function authenticate()
	{
		if(!$this->storage->get('access_token')){
			if(!$this->storage->get('request_token')){
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
		if(!$token = $this->storage->get('access_token')){
			if(!$token = $this->storage->get('request_token')){
				$token = new \stdClass();
				$token->oauth_token = null;
				$token->oauth_token_secret = null;
			}
		}
		return $token;
	}
	
	/**
	 * Generate signed request URL
	 * See inline comments for description
	 * @link http://oauth.net/core/1.0/#signing_process
	 */
	protected function getSignedRequest($method, $url, $call, array $additional = array())
	{
		// Get the request/access token
		$token = $this->getToken();
		
		// Prepare the standard request parameters
		$params = array(
			'oauth_consumer_key' => $this->consumerKey,
			'oauth_token' => $token->oauth_token,
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => time(),
			'oauth_nonce' => md5(microtime(true)),
			'oauth_version' => '1.0'
		);
	
		// Merge with the additional request parameters
		$params = array_merge($params, $additional);
		ksort($params);
	
		// URL encode each parameter to RFC3986 for use in the base string
		$encoded = array();
		foreach($params as $param => $value){
			if($value !== null){
				if($value[0] === '@') $value = substr($value, 1);
				$encoded[] = $this->encode($param) . '=' . $this->encode($value);
			}
		}
	
		// Build the first part of the string
		$base = $method . '&' . urlencode($url . $call) . '&';
		
		// Re-encode the encoded parameter string and append to $base
		$base .= $this->encode(implode('&', $encoded));

		// Build the secret key used for generating the HMAC digest
		$key = $this->consumerSecret . '&' . $token->oauth_token_secret;
		
		// Generate a base64 encoded, keyed hash
		$signature = base64_encode(hash_hmac('sha1', $base, $key, true));
		$params['oauth_signature'] = $signature;
	
		// Build the signed request URL
		$query = '?' . http_build_query($params);
		return $url . $call . $query;
	}
	
	/**
	 * Encode a value to RFC3986
	 * This is a convenience method to decode ~ symbols encoded
	 * by rawurldecode. This will encode all characters except
	 * the unreserved set, ALPHA, DIGIT, '-', '.', '_', '~'
	 * @link http://oauth.net/core/1.0/#encoding_parameters
	 * @param unknown_type $value
	 */
	private function encode($value)
	{
		return str_replace('%7E', '~', rawurlencode($value));
	}
}
