<?php

/**
 * OAuth storage handler using PHP sessions
 * This is a per session storage handler, meaning that you will need
 * to authorise the Dropbox app if the session ends (browser is closed, 
 * session times out etc). For persistent storage of OAuth tokens, 
 * please use \Dropbox\OAuth\Storage\PDO as your storage handler
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @package Dropbox\Oauth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

class Session implements StorageInterface
{
    /**
     * Session namespace
     * @var string
     */
    protected $namespace = 'dropbox_api';
    
    /**
     * Encyption object
     * @var Encrypter|null
     */
    protected $encrypter = null;
    
    /**
     * Authenticated user ID
     * @var mixed
     */
    protected $userID = null;
    
    /**
     * Check if a session has been started (start one where appropriate)
     * and if an instance of the encrypter is passed, set the encryption object
     * @return void
     */
    public function __construct(Encrypter $encrypter = null, $userID = null)
    {
    	// If no session is started, start one
        if (session_id() == '') {
            session_start();
        }
        
        // Set the encrypter object if required
        if ($encrypter instanceof Encrypter) {
            $this->encrypter = $encrypter;
        }
        
        // Set the authenticated user ID
        $this->userID = $userID;
    }
    
    /**
     * Set the session namespace
     * $namespace corresponds to $_SESSION[$namespace] 
     * @param string $namespace
     * @return void
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }
    
    /**
     * Get an OAuth token from the session
     * If the encrpytion object is set then decrypt the token before returning
     * @param string $type Token type to retrieve
     * @return array|bool
     */
    public function get($type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new \Dropbox\Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } else {
            if (isset($_SESSION[$this->namespace][$this->userID][$type])) {
                $token = $this->decrypt($_SESSION[$this->namespace][$this->userID][$type]);
                return $token;
            }
            return false;
        }
    }
    
    /**
     * Set an OAuth token in the session by type
     * If the encryption object is set then encrypt the token before storing
     * @param \stdClass Token object to set
     * @param string $type Token type
     * @return void
     */
    public function set($token, $type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new \Dropbox\Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } else {
            $token = $this->encrypt($token);
            $_SESSION[$this->namespace][$this->userID][$type] = $token;
        }
    }
    
    /**
     * Delete the request and access tokens currently stored in the session
     * @return bool
     */
    public function delete()
    {
        unset($_SESSION[$this->namespace][$this->userID]);
        return true;
    }
    
    /**
     * Use the Encrypter to encrypt a token and return it
     * If there is not encrypter object, return just the 
     * serialized token object for storage
     * @param stdClass $token OAuth token to encrypt
     * @return stdClass|string
     */
    protected function encrypt($token)
    {
        // Serialize the token object
        $token = serialize($token);
        
        // Encrypt the token if there is an Encrypter instance
        if ($this->encrypter instanceof Encrypter) {
            $token = $this->encrypter->encrypt($token);
        }
        
        // Return the token
        return $token;
    }
    
    /**
     * Decrypt a token using the Encrypter object and return it
     * If there is no Encrypter object, assume the token was stored
     * serialized and return the unserialized token object
     * @param stdClass $token OAuth token to encrypt
     * @return stdClass|string
     */
    protected function decrypt($token)
    {
        // Decrypt the token if there is an Encrypter instance
        if ($this->encrypter instanceof Encrypter) {
            $token = $this->encrypter->decrypt($token);
        }
        
        // Return the unserialized token
        return @unserialize($token);
    }
}
