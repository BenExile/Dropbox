### Introduction

This repository contains a PHP SDK that provides access to the [Dropbox REST API][].
The SDK conforms to the [PSR-0 standard][] for autoloading interoperability and requires PHP >= 5.3.1. 
Unless otherwise stated, all components of the SDK are licensed under the [MIT License][].

### Requirements

* PHP >= 5.3.1
* [PHP cURL][] (\Dropbox\OAuth\Consumer\Curl)
* [PHP Mcrypt][] (\Dropbox\OAuth\Storage\Encrypter)

### Known Issues

* Due to [PHP Bug #48962][] affecting cURL, PHP >= 5.3.1 must be used until further consumers are available

### Usage & Examples

Please see the [examples provided][].

### Show Your Support

If you'd like to show your support, [donations][] of alcoholic beverages are much appreciated and help me code harder &gt;:D. You can also endorse me using the link below and help me earn badges and geek cred on Coderwall.

[![endorse](http://api.coderwall.com/benthedesigner/endorsecount.png)](http://coderwall.com/benthedesigner) 

[beta methods]: https://www.dropbox.com/developers/reference/api#beta
[Dropbox REST API]: https://www.dropbox.com/developers/reference/api
[PSR-0 standard]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[MIT License]: https://github.com/BenTheDesigner/Dropbox/blob/master/mit-license.md
[PHP cURL]: http://www.php.net/manual/en/book.curl.php
[PHP Mcrypt]: http://php.net/manual/en/book.mcrypt.php
[PHP Bug #48962]: https://bugs.php.net/bug.php?id=48962
[examples provided]: https://github.com/BenTheDesigner/Dropbox/tree/master/examples
[donations]: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YQJX52Q6S54HA