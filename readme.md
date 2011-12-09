### Introduction

This is a PHP implementation of the [Dropbox REST API][].

This library conforms to the [PSR-0 standard][] for autoloading interoperability and requires PHP >= 5.3.0.

### Requirements

* PHP >= 5.3.0
* [PHP cURL][]

### Example Usage

```php
<?php

// Requires won't be used in autoloading environment
require_once('Dropbox/API.php');
require_once('Dropbox/OAuth/Consumer/ConsumerAbstract.php');
require_once('Dropbox/OAuth/Consumer/Curl.php');
require_once('Dropbox/OAuth/Storage/StorageInterface.php');
require_once('Dropbox/OAuth/Storage/Session.php');

$key      = 'xxxxxxxxxxxxxxx';  // Your Consumer Key
$secret   = 'xxxxxxxxxxxxxxx';  // Your Consumer Secret
$callback = 'http://localhost'; // Your Authorisation Callback URL

$storage = new \Dropbox\OAuth\Storage\Session;
$OAuth = new \Dropbox\OAuth\Consumer\Curl($key, $secret, $storage, $callback);
$dropbox = new \Dropbox\API($OAuth);

// Upload the readme for this lib to the root of your dropbox
$put = $dropbox->putFile(realpath('readme.md'));
var_dump($put);
```

[Dropbox REST API]: https://www.dropbox.com/developers/reference/api
[PSR-0 standard]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[PHP cURL]: http://www.php.net/manual/en/book.curl.php