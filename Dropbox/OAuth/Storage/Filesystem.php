<?php

/**
 * OAuth storage handler built using the filesystem
 * @author Jonas Schmid <jonas.schmid@gmail.com>
 * @link https://github.com/jschmid/dropbox
 * @package Dropbox\Oauth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

class Filesystem extends Session
{
    /**
     * Authenticated user ID
     * @var int
     */
    private $userID = null;
    
    /**
     * Associative array of PDO connection options
     * @var string
     */
    private $sessionsFolder = "oauthTokens";
    
    private $userFilename;
    
    /**
     * Construct the parent object and
     * set the authenticated user ID
     * @param \Dropbox\OAuth\Storage\Encrypter $encrypter
     * @param int $userID
     * @throws \Dropbox\Exception
     */
    public function __construct(Encrypter $encrypter = null, $userID)
    {
        // Create the folder if needed, throw an Exception if not possible
        if (!is_dir($this->sessionsFolder)) {
            if(!mkdir($this->sessionsFolder)) {
              throw new \Dropbox\Exception('Could not create a folder to store sessions.');
            }
        }
        
        // Construct the parent object so we can access the SESSION
        // instead of reading the file on every request
        parent::__construct($encrypter);
        
        // Set the authenticated user ID
        $this->userID = $userID;
        $this->userFilename = $this->sessionsFolder . '/' . $this->userID;
    }
    
    /**
     * Get an OAuth token from the file or session (see below)
     * Request tokens are stored in the session, access tokens in the file
     * Once a token is retrieved it will be stored in the user's session
     * for subsequent requests to reduce overheads
     * @param string $type Token type to retrieve
     * @return array|bool
     */
    public function get($type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new \Dropbox\Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } elseif ($type == 'request_token') {
            return parent::get($type);
        } elseif ($token = parent::get($type)) {
            return $token;
        } else {
            if(file_exists($this->userFilename)) {
                $filecontent = file_get_contents($this->userFilename);
                $_SESSION[$this->namespace][$type] = $filecontent;
                $token = $this->decrypt($filecontent);
                return $token;
            }
            return false;
        }
    }
    
    /**
     * Set an OAuth token in the database or session (see below)
     * Request tokens are stored in the session, access tokens in the database
     * @param \stdClass Token object to set
     * @param string $type Token type
     * @return void
     */
    public function set($token, $type)
    {
        if ($type != 'request_token' && $type != 'access_token') {
            throw new \Dropbox\Exception("Expected a type of either 'request_token' or 'access_token', got '$type'");
        } elseif ($type == 'request_token') {
            parent::set($token, $type);
        } else {
            $token = $this->encrypt($token);
            file_put_contents($this->userFilename, $token);
            $_SESSION[$this->namespace][$type] = $token;
        }
    }
}
