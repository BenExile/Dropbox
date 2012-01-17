### Introduction

This repository contains a PHP SDK that provides access to the [Dropbox REST API][]. The SDK conforms to the [PSR-0 standard][] for autoloading interoperability and requires PHP >= 5.3.0. Unless otherwise stated, all components of the SDK are licensed under the [MIT License][].

### Requirements

* PHP >= 5.3.0
* [PHP cURL][]

### Example Usage

```php
<?php

// Register a simple autoload function
spl_autoload_register(function($class){
	require_once('path/to/Dropbox/' . $class . '.php');
});

// Set your consumer key, secret and callback URL
$key      = 'XXXXXXXXXXXXXXX';
$secret   = 'XXXXXXXXXXXXXXX';
$callback = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Instantiate the required Dropbox objects
$storage = new \Dropbox\OAuth\Storage\Session;
$OAuth   = new \Dropbox\OAuth\Consumer\Curl($key, $secret, $storage, $callback);
$dropbox = new \Dropbox\API($OAuth);

// Upload the readme for this lib to the root of your dropbox
$upload = $dropbox->putFile(realpath('readme.md'));
var_dump($upload);
```

[Dropbox REST API]: https://www.dropbox.com/developers/reference/api
[PSR-0 standard]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[MIT License]: https://github.com/BenTheDesigner/Dropbox/blob/master/mit-license.md
[PHP cURL]: http://www.php.net/manual/en/book.curl.php