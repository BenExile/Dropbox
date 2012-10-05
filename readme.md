### Introduction

This repository contains a PHP SDK that provides access to the [Dropbox REST API][].
The SDK conforms to the [PSR-0 standard][] for autoloading interoperability and requires PHP >= 5.3.1. 
Unless otherwise stated, all components of the SDK are licensed under the [MIT License][].

### Requirements

* PHP >= 5.3.1
* [PHP cURL][] (\Dropbox\OAuth\Consumer\Curl)
* [PHP Mcrypt][] (\Dropbox\OAuth\Storage\Encrypter)
* [PHP PDO][] (\Dropbox\OAuth\Storage\DB)

### Known Issues

* A recent change to the PDO storage handler will require a calling code change or end users will need to re-authorise the application. See [here][PDO handler change] for more information.
* Due to [PHP Bug #48962][] affecting cURL, PHP >= 5.3.1 must be used until further consumers are available

### Usage & Examples

Please see the [examples provided][].

[Dropbox REST API]: https://www.dropbox.com/developers/reference/api
[PSR-0 standard]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[MIT License]: https://github.com/BenTheDesigner/Dropbox/blob/master/mit-license.md
[PHP cURL]: http://www.php.net/manual/en/book.curl.php
[PHP Mcrypt]: http://php.net/manual/en/book.mcrypt.php
[PHP PDO]: http://php.net/manual/en/book.pdo.php
[PHP Bug #48962]: https://bugs.php.net/bug.php?id=48962
[examples provided]: https://github.com/BenTheDesigner/Dropbox/tree/master/examples
[PDO handler change]: https://github.com/BenTheDesigner/Dropbox/commit/d407b7cf332877491e2c7e108a30102dd61d481b#commitcomment-1936563