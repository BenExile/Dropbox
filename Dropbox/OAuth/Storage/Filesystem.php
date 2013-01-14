<?php

/**
 * OAuth storage handler built using the filesystem
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @author Jonas Schmid <jonas.schmid@gmail.com>
 * @link https://github.com/benthedesigner/dropbox
 * @package Dropbox\Oauth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

class Filesystem extends Session
{
    /**
     * Folder to store OAuth token files
     * @see \Dropbox\OAuth\Storage\Filesystem::setDirectory();
     * @var null|string
     */
    private $tokenDirectory = null;
    
    /**
     * Construct the parent object and
     * set the authenticated user ID
     * @param \Dropbox\OAuth\Storage\Encrypter $encrypter
     * @param int $userID
     * @throws \Dropbox\Exception
     */
    public function __construct(Encrypter $encrypter = null, $userID)
    {
        // Construct the parent object so we can access the SESSION
        // instead of reading the file on every request
        parent::__construct($encrypter, $userID);
    }
    
    /**
     * Set the directory to store OAuth tokens
     * This method MUST be called after instantiating the storage
     * handler to avoid creating tokens in potentially vulnerable
     * locations (i.e. inside web root)
     * @param string $dir Path to token storage directory
     */
    public function setDirectory($dir)
    {
        if(!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \Dropbox\Exception('Unable to create directory ' . $dir);
        } else {
            $this->tokenDirectory = $dir;
        }
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
            $file = $this->getTokenFilePath();
            if(file_exists($file) && $token = file_get_contents($file)) {
                $_SESSION[$this->namespace][$this->userID][$type] = $token;
                return $this->decrypt($token);
            }
            return false;
        }
    }
    
    /**
     * Set an OAuth token in the file or session (see below)
     * Request tokens are stored in the session, access tokens in the file
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
            $file = $this->getTokenFilePath();
            file_put_contents($file, $token);
            $_SESSION[$this->namespace][$this->userID][$type] = $token;
        }
    }
    
    /**
     * Delete the access token stored on disk for the current user ID
     * @return bool
     */
    public function delete()
    {
        parent::delete();
        $file = $this->getTokenFilePath();
        return file_exists($file) && @unlink($file);
    }
    
    /**
     * Get the token file path for the specified user ID
     * @return string
     */
    private function getTokenFilePath()
    {
        if ($this->tokenDirectory === null) {
            throw new \Dropbox\Exception('OAuth token directory not set. See Filesystem::setDirectory()');
        } else {
            return $this->tokenDirectory . '/' . md5($this->userID) . '.token';
        }
    }
}
