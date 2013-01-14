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
     * Associative array of PDO connection options
     * @var array
     */
    protected $options = array(
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    );
    
    /**
     * Forward-declare PDO object
     * @var null|PDO
     */
    protected $pdo = null;
    
    /**
     * Default database table
     * Override this using setTable()
     * @var string
     */
    protected $table = 'dropbox_oauth_tokens';
    
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
        parent::__construct($encrypter, $userID);
    }
    
    /**
     * Connect to the database
     * @param string $host Database server hostname
     * @param string $db Database to connect to
     * @param string $user Database username
     * @param string $pass Database user password
     * @param int $port Database server port (Default: 3306)
     * @return void
     */
    public function connect($host, $db, $user, $pass, $port = 3306)
    {
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db;
        $this->pdo = new \PDO($dsn, $user, $pass, $this->options);
    }
    
    /**
     * Set the table to store OAuth tokens in
     * If the table does not exist, the get() method will attempt to create it when it is called.
     * @todo Check for valid table name and quote it (see below)
     * @link http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
     * @return void
     */
    public function setTable($table)
    {
        $this->table = $table;
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
            try {
                $query = 'SELECT uid, userID, token FROM ' . $this->table . ' WHERE userID = ? LIMIT 1';
                $stmt = $this->pdo->prepare($query);
                $stmt->execute(array($this->userID));
                if ($result = $stmt->fetch()) {
                    $token = $this->decrypt($result['token']);
                    $_SESSION[$this->namespace][$this->userID][$type] = $result['token'];
                    return $token;
                }
            } catch (\PDOException $e) {
                // Fetch error information from the statement handle
                $errorInfo = $stmt->errorInfo();

                // Handle the PDOException based on the error code
                switch ($errorInfo[1]) {
                    case 1146: // Table does not exist
                        $this->createTable();
                        break;
                    default: // Rethrow the PDOException
                        throw $e;
                }
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
            $message = "Expected a type of either 'request_token' or 'access_token', got '$type'";
            throw new \Dropbox\Exception($message);
        } elseif ($type == 'request_token') {
            parent::set($token, $type);
        } else {
            $query = 'INSERT INTO ' . $this->table . ' (userID, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token = ?';
            $stmt = $this->pdo->prepare($query);
            $token = $this->encrypt($token);
            $stmt->execute(array($this->userID, $token, $token));
            $_SESSION[$this->namespace][$this->userID][$type] = $token;
        }
    }
    
    /**
     * Delete access token for the current user ID from the database
     * @todo Add error checking
     * @return bool
     */
    public function delete()
    {
        try {
            parent::delete();
            $query = 'DELETE FROM ' . $this->table . ' WHERE userID = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(array($this->userID));
            return $stmt->rowCount() > 0;
        } catch(\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Attempt to create the OAuth token table
     * @return void
     */
    protected function createTable()
    {
        $template = file_get_contents(dirname(__FILE__) . '/TableSchema.sql');
        $this->pdo->query(sprintf($template, $this->table));
    }
}
