<?php

declare(strict_types=1);

namespace TidalApi;

class Session
{
    protected string $accessToken = '';

    protected string $clientId = '';

    protected string $clientSecret = '';

    protected int $expirationTime = 0;

    protected string $redirectUri = '';

    protected string $refreshToken = '';

    protected string $scope = '';

    protected ?Request $request = null;

    /**
     * Constructor
     * Set up client credentials.
     *
     * @param  string  $clientId  The client ID.
     * @param  string  $clientSecret  Optional. The client secret.
     * @param  string  $redirectUri  Optional. The redirect URI.
     * @param  ?Request  $request  Optional. The Request object to use.
     */
    public function __construct(
        string $clientId,
        string $clientSecret = '',
        string $redirectUri = '',
        ?Request $request = null
    ) {
        $this->setClientId($clientId);
        $this->setClientSecret($clientSecret);
        $this->setRedirectUri($redirectUri);

        $this->request = $request ?? new Request;
    }

    /**
     * Generate a code challenge from a code verifier for use with the PKCE flow.
     *
     * @param  string  $codeVerifier  The code verifier to create a challenge from.
     * @param  string  $hashAlgo  Optional. The hash algorithm to use. Defaults to "sha256".
     * @return string The code challenge.
     */
    public function generateCodeChallenge(string $codeVerifier, string $hashAlgo = 'sha256'): string
    {
        $challenge = hash($hashAlgo, $codeVerifier, true);
        $challenge = base64_encode($challenge);
        $challenge = strtr($challenge, '+/', '-_');
        $challenge = rtrim($challenge, '=');

        return $challenge;
    }

    /**
     * Generate a code verifier for use with the PKCE flow.
     *
     * @param  int  $length  Optional. Code verifier length. Must be between 43 and 128 characters long, default is 128.
     * @return string A code verifier string.
     */
    public function generateCodeVerifier(int $length = 128): string
    {
        return $this->generateState($length);
    }

    /**
     * Generate a random state value.
     *
     * @param  int  $length  Optional. Length of the state. Default is 16 characters.
     * @return string A random state value.
     */
    public function generateState(int $length = 16): string
    {
        // Length will be doubled when converting to hex
        return bin2hex(
            random_bytes($length / 2)
        );
    }

    /**
     * Get the authorization URL.
     *
     * @param  array|object  $options  Optional. Options for the authorization URL.
     *                                 - string code_challenge. A PKCE code challenge.
     *                                 - array scope Optional. Scope(s) to request from the user.
     *                                 - string state Optional. A CSRF token.
     * @return string The authorization URL.
     */
    public function getAuthorizeUrl(array|object $options = []): string
    {
        $options = (array) $options;

        $parameters = [
            'response_type' => 'code',
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => isset($options['scope']) ? implode(' ', $options['scope']) : null,
            'code_challenge' => $options['code_challenge'],
            'code_challenge_method' => $options['code_challenge_method'] ?? 'S256',
            'state' => $options['state'] ?? null,
        ];

        return Request::LOGIN_URL.'/authorize?'.http_build_query($parameters, '', '&');
    }

    /**
     * Get the client ID.
     *
     * @return string The client ID.
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Set the client ID.
     *
     * @param  string  $clientId  The client ID.
     */
    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * Get the client's redirect URI.
     *
     * @return string The redirect URI.
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Set the client's redirect URI.
     *
     * @param  string  $redirectUri  The redirect URI.
     */
    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    /**
     * Get the access token.
     *
     * @return string The access token.
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * Set the access token.
     *
     * @param  string  $accessToken  The access token
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get the access token expiration time.
     *
     * @return int A Unix timestamp indicating the token expiration time.
     */
    public function getTokenExpiration(): int
    {
        return $this->expirationTime;
    }

    /**
     * Get the refresh token.
     *
     * @return string The refresh token.
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * Set the session's refresh token.
     *
     * @param  string  $refreshToken  The refresh token.
     */
    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get the scope for the current access token
     *
     * @return array The scope for the current access token
     */
    public function getScope(): array
    {
        return explode(' ', $this->scope);
    }

    /**
     * Refresh an access token.
     *
     * @param  string|null  $refreshToken  Optional. The refresh token to use.
     * @return bool Whether the access token was successfully refreshed.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function refreshAccessToken(?string $refreshToken = null): bool
    {
        $parameters = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken ?? $this->refreshToken,
        ];

        $headers = [];
        if ($this->getClientSecret()) {
            $payload = base64_encode($this->getClientId().':'.$this->getClientSecret());

            $headers = [
                'Authorization' => 'Basic '.$payload,
            ];
        }

        ['body' => $response] = $this->request->auth('POST', '/v1/oauth2/token', $parameters, $headers);

        if (isset($response->access_token)) {
            $this->accessToken = $response->access_token;
            $this->expirationTime = time() + $response->expires_in;
            $this->scope = $response->scope ?? $this->scope;

            if (isset($response->refresh_token)) {
                $this->refreshToken = $response->refresh_token;
            } elseif (empty($this->refreshToken)) {
                $this->refreshToken = $refreshToken;
            }

            return true;
        }

        return false;
    }

    /**
     * Get the client secret.
     *
     * @return string The client secret.
     */
    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Set the client secret.
     *
     * @param  string  $clientSecret  The client secret.
     */
    public function setClientSecret(string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * Request an access token given an authorization code.
     *
     * @param  string  $authorizationCode  The authorization code from Spotify.
     * @param  string  $codeVerifier  Optional. A previously generated code verifier. Will assume a PKCE flow if passed.
     * @return bool True when the access token was successfully granted, false otherwise.
     */
    public function requestAccessToken(string $authorizationCode, string $codeVerifier = ''): bool
    {
        $parameters = [
            'client_id' => $this->getClientId(),
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectUri(),
            'code_verifier' => $codeVerifier,
        ];

        ['body' => $response] = $this->request->auth('POST', '/v1/oauth2/token', $parameters, []);

        if (isset($response->refresh_token) && isset($response->access_token)) {
            $this->refreshToken = $response->refresh_token;
            $this->accessToken = $response->access_token;
            $this->expirationTime = time() + $response->expires_in;
            $this->scope = $response->scope ?? $this->scope;

            return true;
        }

        return false;
    }

    /**
     * Request an access token using the Client Credentials Flow.
     *
     * @return bool True when an access token was successfully granted, false otherwise.
     */
    public function requestCredentialsToken(): bool
    {
        $payload = base64_encode($this->getClientId().':'.$this->getClientSecret());

        $parameters = [
            'grant_type' => 'client_credentials',
        ];

        $headers = [
            'Authorization' => 'Basic '.$payload,
        ];

        ['body' => $response] = $this->request->auth('POST', '/v1/oauth2/token', $parameters, $headers);

        if (isset($response->access_token)) {
            $this->accessToken = $response->access_token;
            $this->expirationTime = time() + $response->expires_in;
            $this->scope = $response->scope ?? $this->scope;

            return true;
        }

        return false;
    }
}
