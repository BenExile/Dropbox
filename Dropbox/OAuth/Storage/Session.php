<?php

/**
 * OAuth storage handler using PHP sessions
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
     * Check if a session has been started (start one where appropriate)
     * and if an instance of the encrypter is passed, set the encryption object
     * @return void
     */
    public function __construct(Encrypter $encrypter = null)
    {
        $id = session_id();
        
        if (empty($id)) {
            session_start();    
        }
        
        if ($encrypter instanceof Encrypter) {
            $this->encrypter = $encrypter;
        }
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
     * If the encrpytion object is set then
     * decrypt the token before returning
     * @param string $type Token type to retrieve
     * @return array|bool
     */
    public function get($type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new \Dropbox\Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } else {
            if (isset($_SESSION[$this->namespace][$type])) {
                $token = $this->decrypt($_SESSION[$this->namespace][$type]);
                return $token;
            }
            return false;
        }
    }
    
    /**
     * Set an OAuth token in the session by type
     * If the encryption object is set then
     * encrypt the token before storing
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
            $_SESSION[$this->namespace][$type] = $token;
        }
    }
    
    /**
     * Use the Encrypter to encrypt a token
     * @param stdClass $token OAuth token to encrypt
     * @return stdClass|string
     */
    protected function encrypt($token)
    {
        if ($this->encrypter instanceof Encrypter) {
            $token = $this->encrypter->encrypt($token);
        }
        return $token;
    }
    
    /**
     * Decrypt a token using the Encrypter object
     * @param stdClass $token OAuth token to encrypt
     * @return stdClass|string
     */
    protected function decrypt($token)
    {
        if ($this->encrypter instanceof Encrypter) {
            $token = $this->encrypter->decrypt($token);
        }
        return $token;
    }
}
