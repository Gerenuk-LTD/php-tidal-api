# PHP Tidal API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gerenuk/php-tidal-api.svg?style=flat-square)](https://packagist.org/packages/gerenuk/php-tidal-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/gerenuk-ltd/php-tidal-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gerenuk-ltd/php-tidal-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/gerenuk-ltd/php-tidal-api/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/gerenuk-ltd/php-tidal-api/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/gerenuk/php-tidal-api.svg?style=flat-square)](https://packagist.org/packages/gerenuk/php-tidal-api)

This is a PHP wrapper for [Tidal's API](https://developer.tidal.com/documentation). It is a modified version of [jwilsson/spotify-web-api-php](https://github.com/jwilsson/spotify-web-api-php).

## Table of Contents
1. [Introduction](#php-tidal-api)
2. [Version Compatability](#version-compatability)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Usage](#usage)
6. [Changelog](#changelog)
7. [Contributing](#contributing)
8. [Security Vulnerabilities](#security-vulnerabilities)
9. [Credits](#credits)
10. [License](#license)

## Version Compatability

| Plugin | PHP |
|--------|-----|
| 1.x    | 8.x |

## Requirements

* PHP 8.0 or later.
* PHP [cURL extension](http://php.net/manual/en/book.curl.php) (Usually included with PHP).

## Installation

You can install the package via composer:

```bash
composer require gerenuk/php-tidal-api
```

## Usage

Before using the Tidal API you will need to create an app at [Tidal's developer website](https://developer.tidal.com/dashboard).

Simple example to retrieve the currently authenticated user's playlists:

### Step 1:

Put the following code in its own file, lets call it `auth.php`. Replace `CLIENT_ID` with the value given to you by Tidal. The `REDIRECT_URI` is the one you entered when creating the Tidal app, make sure it's an exact match. You'll also need to create a *code verifier* and store it somewhere between requests. It will be used again in the second step.

```php
require 'vendor/autoload.php';

$session = new TidalApi\Session(
    'CLIENT_ID',
    '', // Normally the client secret, but this value can be omitted when using the PKCE flow.
    'REDIRECT_URI'
);

$verifier = $session->generateCodeVerifier(); // Store this value somewhere, a session for example.
$challenge = $session->generateCodeChallenge($verifier);
$state = $session->generateState();

$options = [
    'code_challenge' => $challenge,
    'scope' => [
        'playlists.read',
    ],
    'state' => $state,
];

header('Location: ' . $session->getAuthorizeUrl($options));
die();
```

__Note:__ The `state` parameter is optional but highly recommended to prevent CSRF attacks. The value will need to be stored between requests and verified when the user is redirected back to your application from Tidal.

### Step 2:

When the user has approved your app, Tidal will redirect the user together with a `code` to the specified redirect URI. You'll need to use this code to request an access token from Tidal. The *code verifier* created in the previous step will also be needed.

__Note:__ The API wrapper does not include any token management. It's up to you to save the access token somewhere (in a database, a PHP session, or wherever appropriate for your application) and request a new access token when the old one has expired.

Let's put this code in a new file called `callback.php`:

```php
require 'vendor/autoload.php';

$session = new TidalApi\Session(
    'CLIENT_ID',
    'CLIENT_SECRET',
    'REDIRECT_URI'
);

$state = $_GET['state'];

// Fetch the stored state value from somewhere. A session for example.

if ($state !== $storedState) {
    // The state returned isn't the same as the one we've stored, we shouldn't continue.
    die('State mismatch');
}

// Request an access token using the code from Spotify and the previously created code verifier.
$session->requestAccessToken($_GET['code'], $verifier);

$accessToken = $session->getAccessToken();
$refreshToken = $session->getRefreshToken();

// Store the access and refresh tokens somewhere. In a session for example.

// Send the user along and fetch some data!
header('Location: app.php');
die();
```

When requesting an access token, a **refresh token** will also be included. This can be used to extend the validity of access tokens. It's recommended to also store this somewhere persistent, in a database for example.

### Step 3:

In a third file, `app.php`, tell the API wrapper which access token to use, and then make some API calls!

```php
require 'vendor/autoload.php';

$api = new TidalApi\TidalApi();

// Fetch the saved access token from somewhere. A session for example.
$api->setAccessToken($accessToken);

// It's now possible to request the currently authenticated user's playlists.
print_r(
    $api->getMyPlaylists()
);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- Modified version of [spotify-web-api-php](https://github.com/jwilsson/spotify-web-api-php) from [jwilsson](https://github.com/jwilsson)
- [Kieran Proctor](https://github.com/KieranLProctor)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
