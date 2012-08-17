<?php

/**
 * This class provides the functionality to encrypt
 * and decrypt access tokens stored by the application
 * @author Ben Tadiar <ben@handcraftedbyben.co.uk>
 * @link https://github.com/benthedesigner/dropbox
 * @package Dropbox\Oauth
 * @subpackage Storage
 */
namespace Dropbox\OAuth\Storage;

class Encrypter
{    
    // Encryption settings - default settings yield encryption to AES (256-bit) standard
    // @todo Provide PHPDOC for each class constant
    const CIPHER = MCRYPT_RIJNDAEL_128;
    const MODE = MCRYPT_MODE_CBC;
    const KEY_SIZE = 32;
    const IV_SIZE = 16;
    const IV_SOURCE = MCRYPT_DEV_URANDOM;
    
    /**
     * Encryption key
     * @var null|string
     */
    private $key = null;
    
    /**
     * Check Mcrypt is loaded and set the encryption key
     * @param string $key
     * @return void
     */
    public function __construct($key)
    {
        if (!extension_loaded('mcrypt')) {
            throw new \Dropbox\Exception('The storage encrypter requires the MCrypt extension');
        } elseif (($length = mb_strlen($key, '8bit')) !== self::KEY_SIZE) {
            throw new \Dropbox\Exception('Expecting a ' .  self::KEY_SIZE . ' byte key, got ' . $length);
        } else {
            // Set the encryption key
            $this->key = $key;
        }
    }
    
    /**
     * Encrypt the OAuth token
     * @param \stdClass $token Serialized token object
     * @return string
     */
    public function encrypt($token)
    {
        $iv = mcrypt_create_iv(self::IV_SIZE, self::IV_SOURCE);
        $cipherText = mcrypt_encrypt(self::CIPHER, $this->key, $token, self::MODE, $iv);
        return base64_encode($iv . $cipherText);
    }
    
    /**
     * Decrypt the ciphertext
     * @param string $cipherText
     * @return object \stdClass Unserialized token
     */
    public function decrypt($cipherText)
    {
        $cipherText = base64_decode($cipherText);
        $iv = substr($cipherText, 0, self::IV_SIZE);
        $cipherText = substr($cipherText, self::IV_SIZE);
        $token = mcrypt_decrypt(self::CIPHER, $this->key, $cipherText, self::MODE, $iv);
        return $token;
    }
}
