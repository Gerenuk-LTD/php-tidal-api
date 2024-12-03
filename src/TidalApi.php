<?php

declare(strict_types=1);

namespace TidalApi;

class TidalApi
{
    protected string $accessToken = '';
    protected array $lastResponse = [];
    protected array $options = [
        'auto_refresh' => false,
        'auto_retry' => false,
        'return_assoc' => false,
    ];
    protected ?Request $request = null;
    protected ?Session $session = null;

    /**
     * Constructor
     * Set options and class instances to use.
     *
     * @param  array|object  $options  Optional. Options to set.
     * @param  ?Session  $session  Optional. The Session object to use.
     * @param  ?Request  $request  Optional. The Request object to use.
     */
    public function __construct(array|object $options = [], ?Session $session = null, ?Request $request = null)
    {
        $this->setOptions($options);
        $this->setSession($session);

        $this->request = $request ?? new Request();
    }

    /**
     * Add authorization headers.
     *
     * @param $headers array. Optional. Additional headers to merge with the authorization headers.
     *
     * @return array Authorization headers, optionally merged with the passed ones.
     */
    protected function authHeaders(array $headers = []): array
    {
        $accessToken = $this->session ? $this->session->getAccessToken() : $this->accessToken;

        if ($accessToken) {
            $headers = array_merge($headers, [
                'Authorization' => 'Bearer ' . $accessToken,
            ]);
        }

        return $headers;
    }

    /**
     * Set the access token to use.
     *
     * @param string $accessToken The access token.
     *
     * @return self
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Set options
     *
     * @param array|object $options Options to set.
     *
     * @return self
     */
    public function setOptions(array|object $options): self
    {
        $this->options = array_merge($this->options, (array) $options);

        return $this;
    }

    /**
     * Set the Session object to use.
     *
     * @param ?Session $session The Session object.
     *
     * @return self
     */
    public function setSession(?Session $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Send a request to the Tidal Api, automatically refreshing the access token as needed.
     *
     * @param string $method The HTTP method to use.
     * @param string $uri The URI to request.
     * @param string|array $parameters Optional. Query string parameters or HTTP body, depending on $method.
     * @param array $headers Optional. HTTP headers.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     *
     * @return array Response data.
     * - array|object body The response body. Type is controlled by the `return_assoc` option.
     * - array headers Response headers.
     * - int status HTTP status code.
     * - string url The requested URL.
     */
    protected function sendRequest(
        string $method,
        string $uri,
        string|array $parameters = [],
        array $headers = []
    ): array {
        $this->request->setOptions([
            'return_assoc' => $this->options['return_assoc'],
        ]);

        try {
            $headers = $this->authHeaders($headers);

            return $this->request->api($method, $uri, $parameters, $headers);
        } catch (TidalApiException $e) {
            if ($this->options['auto_refresh'] && $e->hasExpiredToken()) {
                $result = $this->session->refreshAccessToken();

                if (!$result) {
                    throw new TidalApiException('Could not refresh access token.');
                }

                return $this->sendRequest($method, $uri, $parameters, $headers);
            } elseif ($this->options['auto_retry'] && $e->isRateLimited()) {
                ['headers' => $lastHeaders] = $this->request->getLastResponse();

                sleep((int) $lastHeaders['retry-after']);

                return $this->sendRequest($method, $uri, $parameters, $headers);
            }

            throw $e;
        }
    }

    /**
     * Convert an array to a comma-separated string. If it's already a string, do nothing.
     *
     * @param array|string $value The value to convert.
     *
     * @return string A comma-separated string.
     */
    protected function toCommaString(string|array $value): string
    {
        if (is_array($value)) {
            return implode(',', $value);
        }

        return $value;
    }

    /**
     * Get the currently authenticated user.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-me-v2&at=THIRD_PARTY
     *
     * @return array|object The currently authenticated user. Type is controlled by the `return_assoc` option.
     */
    public function me(): array|object
    {
        $uri = '/v2/users/me';

        $this->lastResponse = $this->sendRequest('GET', $uri);

        return $this->lastResponse['body'];
    }
}
