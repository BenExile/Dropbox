<?php

/**
 * OAuth storage handler interface
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @package Dropbox\OAuth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

interface StorageInterface
{
	public function get($type);
	public function set($token, $type);
}
