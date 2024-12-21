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
     * Set up client credentials.
     */
    public function __construct(
        string $clientId,
        string $clientSecret = '',
        string $redirectUri = '',
        ?Request $request = null,
    ) {
        $this->setClientId($clientId);
        $this->setClientSecret($clientSecret);
        $this->setRedirectUri($redirectUri);

        $this->request = $request ?? new Request();
    }

    /**
     * Generate a code challenge from a code verifier for use with the PKCE flow.
     *
     *
     * @api
     */
    public function generateCodeChallenge(string $codeVerifier, string $hashAlgo = 'sha256'): string
    {
        $challenge = hash($hashAlgo, $codeVerifier, true);
        $challenge = base64_encode($challenge);
        $challenge = strtr($challenge, '+/', '-_');

        return rtrim($challenge, '=');
    }

    /**
     * Generate a code verifier for use with the PKCE flow.
     *
     * @throws \Random\RandomException
     *
     * @api
     */
    public function generateCodeVerifier(int $length = 128): string
    {
        return $this->generateState($length);
    }

    /**
     * Generate a random state value.
     *
     * @throws \Random\RandomException
     */
    public function generateState(int $length = 16): string
    {
        // Length will be doubled when converting to hex
        return bin2hex(
            random_bytes($length / 2),
        );
    }

    /**
     * Get the authorization URL.
     *
     *
     * @api
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

        return Request::LOGIN_URL . '/authorize?' . http_build_query($parameters, '', '&');
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Set the client ID.
     *
     * @return $this
     */
    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * Get the client's redirect URI.
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Set the client's redirect URI.
     *
     * @return $this
     */
    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    /**
     * Get the access token.
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * Set the access token.
     *
     * @return $this
     *
     * @api
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get the access token expiration time.
     *
     *
     * @api
     */
    public function getTokenExpiration(): int
    {
        return $this->expirationTime;
    }

    /**
     * Get the refresh token.
     *
     *
     * @api
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * Set the session's refresh token.
     *
     * @return $this
     *
     * @api
     */
    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get the scope for the current access token.
     *
     *
     * @api
     */
    public function getScope(): array
    {
        return explode(' ', $this->scope);
    }

    /**
     * Refresh an access token.
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
            $payload = base64_encode($this->getClientId() . ':' . $this->getClientSecret());

            $headers = [
                'Authorization' => 'Basic ' . $payload,
            ];
        }

        ['body' => $response] = $this->request->auth('POST', '/v1/oauth2/token', $parameters, $headers);

        if (! isset($response->access_token)) {
            return false;
        }

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

    /**
     * Get the client secret.
     */
    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Set the client secret.
     *
     * @return $this
     *
     * @api
     */
    public function setClientSecret(string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * Request an access token given an authorization code.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function requestAccessToken(string $authorizationCode, string $codeVerifier): bool
    {
        $parameters = [
            'client_id' => $this->getClientId(),
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectUri(),
            'code_verifier' => $codeVerifier,
        ];

        ['body' => $response] = $this->request->auth('POST', '/v1/oauth2/token', $parameters);

        if (! isset($response->refresh_token) && ! isset($response->access_token)) {
            return false;
        }

        $this->refreshToken = $response->refresh_token;
        $this->accessToken = $response->access_token;
        $this->expirationTime = time() + $response->expires_in;
        $this->scope = $response->scope ?? $this->scope;

        return true;
    }

    /**
     * Request an access token using the Client Credentials Flow.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function requestCredentialsToken(): bool
    {
        $payload = base64_encode($this->getClientId() . ':' . $this->getClientSecret());

        $parameters = [
            'grant_type' => 'client_credentials',
        ];

        $headers = [
            'Authorization' => 'Basic ' . $payload,
        ];

        ['body' => $response] = $this->request->auth('POST', '/v1/oauth2/token', $parameters, $headers);

        if (! isset($response->access_token)) {
            return false;
        }

        $this->accessToken = $response->access_token;
        $this->expirationTime = time() + $response->expires_in;
        $this->scope = $response->scope ?? $this->scope;

        return true;
    }
}
