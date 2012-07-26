#### 1. Create a Dropbox App
You will need to log in to your Dropbox account and [create a new app][].

#### 2. Set your app key/secret
Replace the [placeholders][] in bootstrap.php with your new app key and secret.

#### 3. Set up the Encrypter object
This is optional (for development purposes) but it is advised that you use the Encrypter in production. OAuth access tokens should be handled sensitively and **never** in plain text.

```
// $key is a 32-byte encryption key (secret)
$key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$encrypter = new \Dropbox\OAuth\Storage\Encrypter($key);
```

#### 4. Set your storage handler
OAuth tokens can currently be stored using one of 2 methods:

>**PHP Sessions**
Tokens will be obtained on a per session basis. This storage handler can be used in cases where there are no user accounts to associate tokens with:

```
// Create the storage object, passing it the Encrypter object
$storage = new \Dropbox\OAuth\Storage\Session($encrypter);
```

>**Database (PDO)**
Tokens will be stored in a database. This handler plugs into existing authentication systems using user ID's already present. It does not provide user registration/management functionality:

```
// Authenticated user ID (stored in SESSION, for example)
$userID = 1;

// Instantiate the storage handler, passing it the Encrypter and user ID
$storage = new \Dropbox\OAuth\Storage\PDO($encrypter, $userID);

// Connect to your datasource
$storage->connect('hostname', 'db', 'username', 'password');
```

#### 5. Instantiate the Consumer and API objects

```
$OAuth = new \Dropbox\OAuth\Consumer\Curl($key, $secret, $storage, $callback);
$dropbox = new \Dropbox\API($OAuth);
```

#### 6. Run the examples
You will now be able to run the examples provided!

[create a new app]: https://www.dropbox.com/developers/apps
[placeholders]: https://github.com/BenTheDesigner/Dropbox/blob/master/examples/bootstrap.php#L30-31