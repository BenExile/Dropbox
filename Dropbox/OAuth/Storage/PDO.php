<?php

/**
 * OAuth storage handler built on PDO
 * @todo Add table creation script
 * @todo Database fallback?
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @package Dropbox\Oauth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

class PDO extends Session
{
    /**
     * Authenticated user ID
     * @var int
     */
    private $userID = null;
    
    /**
     * Associative array of PDO connection options
     * @var array
     */
    private $options = array(
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    );
    
    /**
     * Forward-declare PDO object
     * @var null|PDO
     */
    private $pdo = null;
    
    /**
     * Construct the parent object and
     * set the authenticated user ID
     * @param \Dropbox\OAuth\Storage\Encrypter $encrypter
     * @param int $userID
     * @throws \Dropbox\Exception
     */
    public function __construct(Encrypter $encrypter = null, $userID)
    {
        // Throw an Exception if PDO is not loaded
        if (!extension_loaded('PDO')) {
            throw new \Dropbox\Exception('This storage handler requires the PDO extension');
        }
        
        // Construct the parent object so we can access the SESSION
        // instead of querying the database on every request
        parent::__construct($encrypter);
        
        // Set the authenticated user ID
        $this->userID = $userID;
    }
    
    /**
     * Connect to the database
     * @param string $host Database server hostname
     * @param string $db Database to connect to
     * @param string $user Database username
     * @param string $pass Database user password
     * @return void
     */
    public function connect($host, $db, $user, $pass)
    {
        $dsn = 'mysql:host=' . $host . ';dbname=' . $db;
        $this->pdo = new \PDO($dsn, $user, $pass, $this->options);
    }
    
    /**
     * Get an OAuth token from the database or session (see below)
     * Request tokens are stored in the session, access tokens in the database
     * Once a token is retrieved it will be stored in the users session
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
            $query = 'SELECT token FROM oauth_tokens WHERE userID = ? LIMIT 1';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(array($this->userID));
            if ($result = $stmt->fetch()) {
                $token = $this->decrypt($result['token']);
                $_SESSION[$this->namespace][$type] = $result['token'];
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
            $query = 'INSERT INTO oauth_tokens (userID, token) VALUES (?, ?)';
            $stmt = $this->pdo->prepare($query);
            $token = $this->encrypt($token);
            $stmt->execute(array($this->userID, $token));
            $_SESSION[$this->namespace][$type] = $token;
        }
    }
}
