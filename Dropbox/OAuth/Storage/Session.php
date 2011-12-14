<?php

/**
 * OAuth storage handler using PHP SESSION's
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @package Dropbox\Oauth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

class Session implements StorageInterface
{
	/*
	 * Session namespace
	 * @var string
	 */
	private $namespace = 'dropbox_api';
	
	/**
	 * Check if a SESSION has been started
	 * @return void
	 */
	public function __construct()
	{
		$id = session_id();
		if(empty($id)) session_start();
	}
	
	/**
	 * Get an OAuth token from the SESSION
	 * @return array|bool
	 */
	public function get()
	{
		if(isset($_SESSION[$this->namespace]['token'])){
			return $_SESSION[$this->namespace]['token'];
		}
		return false;
	}
	
	/**
	 * Set an OAuth token in the SESSION
	 * @return void
	 */
	public function set($token)
	{
		$_SESSION[$this->namespace]['token'] = $token;
	}
}
