<?php

declare(strict_types=1);

namespace TidalApi;

class Request
{
    public const LOGIN_URL = 'https://login.tidal.com';

    public const AUTH_URL = 'https://auth.tidal.com';

    public const API_URL = 'https://openapi.tidal.com';

    protected array $lastResponse = [];

    protected array $options = [
        'curl_options' => [],
        'return_assoc' => false,
    ];

    /**
     * Make a request to the "login" endpoint.
     *
     * @api
     *
     * @param  string  $method  The HTTP method to use.
     * @param  string  $uri  The URI to request.
     * @param  string|array  $parameters  Optional. Query string parameters or HTTP body, depending on $method.
     * @param  array  $headers  Optional. HTTP headers.
     *
     * @return array Response data.
     *               - array|object body The response body. Type is controlled by the `return_assoc` option.
     *               - array headers Response headers.
     *               - int status HTTP status code.
     *               - string url The requested URL.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function login(string $method, string $uri, string|array $parameters = [], array $headers = []): array
    {
        return $this->send($method, self::LOGIN_URL . $uri, $parameters, $headers);
    }

    /**
     * Make a request to Tidal.
     * You'll probably want to use one of the convenience methods instead.
     *
     * @api
     *
     * @param  string  $method  The HTTP method to use.
     * @param  string  $url  The URL to request.
     * @param  string|array|object  $parameters  Optional. Query string parameters or HTTP body, depending on $method.
     * @param  array  $headers  Optional. HTTP headers.
     *
     * @return array Response data.
     *               - array|object body The response body. Type is controlled by the `return_assoc` option.
     *               - array headers Response headers.
     *               - int status HTTP status code.
     *               - string url The requested URL.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function send(string $method, string $url, string|array|object $parameters = [], array $headers = []): array
    {
        // Reset any old responses
        $this->lastResponse = [];

        // Sometimes a stringified JSON object is passed
        if (is_array($parameters) || is_object($parameters)) {
            $parameters = http_build_query($parameters, '', '&');
        }

        $options = [
            CURLOPT_ENCODING => '',
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => rtrim($url, '/'),
        ];

        foreach ($headers as $key => $val) {
            $options[CURLOPT_HTTPHEADER][] = "$key: $val";
        }

        $method = strtoupper($method);

        switch ($method) {
            case 'DELETE':
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = $method;
                $options[CURLOPT_POSTFIELDS] = $parameters;

                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $parameters;

                break;
            default:
                $options[CURLOPT_CUSTOMREQUEST] = $method;

                if ($parameters) {
                    $options[CURLOPT_URL] .= '?' . $parameters;
                }

                break;
        }

        $ch = curl_init();

        curl_setopt_array($ch, array_replace($options, $this->options['curl_options']));

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new TidalApiException('cURL transport error: ' . $errno . ' ' . $error);
        }

        [$headers, $body] = $this->splitResponse($response);

        $parsedBody = $this->parseBody($body);
        $parsedHeaders = $this->parseHeaders($headers);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        $this->lastResponse = [
            'body' => $parsedBody,
            'headers' => $parsedHeaders,
            'status' => $status,
            'url' => $url,
        ];

        curl_close($ch);

        if ($status >= 400) {
            $this->handleResponseError($body, $status);
        }

        return $this->lastResponse;
    }

    /**
     * Split response into headers and body, taking proxy response headers etc. into account.
     *
     * @internal
     *
     * @param  string  $response  The complete response.
     * @return array An array consisting of two elements, headers and body.
     */
    protected function splitResponse(string $response): array
    {
        $response = str_replace("\r\n", "\n", $response);
        $parts = explode("\n\n", $response, 3);

        // Skip first set of headers for proxied requests etc.
        if (
            preg_match('/^HTTP\/1.\d 100 Continue/', $parts[0]) ||
            preg_match('/^HTTP\/1.\d 200 Connection established/', $parts[0]) ||
            preg_match('/^HTTP\/1.\d 200 Tunnel established/', $parts[0])
        ) {
            return [
                $parts[1],
                $parts[2],
            ];
        }

        return [
            $parts[0],
            $parts[1],
        ];
    }

    /**
     * Parse HTTP response body, taking the "return_assoc" option into account.
     *
     * @internal
     */
    protected function parseBody(string $body): mixed
    {
        return json_decode($body, $this->options['return_assoc']);
    }

    /**
     * Parse HTTP response headers and normalize names.
     *
     * @internal
     *
     * @param  string  $headers  The raw, unparsed response headers.
     *
     * @return array Headers as key–value pairs.
     */
    protected function parseHeaders(string $headers): array
    {
        $headers = explode("\n", $headers);

        array_shift($headers);

        $parsedHeaders = [];
        foreach ($headers as $header) {
            [$key, $value] = explode(':', $header, 2);

            $key = strtolower($key);
            $parsedHeaders[$key] = trim($value);
        }

        return $parsedHeaders;
    }

    /**
     * Handle response errors.
     *
     * @internal
     *
     * @param  string  $body  The raw, unparsed response body.
     * @param  int  $status  The HTTP status code, passed along to any exceptions thrown.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    protected function handleResponseError(string $body, int $status): void
    {
        $parsedBody = json_decode($body);
        $error = $parsedBody->error ?? null;

        if (isset($error->message) && isset($error->status)) {
            // It's an Api call error
            $exception = new TidalApiException($error->message, $error->status);

            if (isset($error->reason)) {
                $exception->setReason($error->reason);
            }

            throw $exception;
        } elseif (isset($parsedBody->error_description)) {
            // It's an auth call error
            throw new TidalApiAuthException($parsedBody->error_description, $status);
        } elseif ($body) {
            // Something else went wrong, try to give at least some info
            throw new TidalApiException($body, $status);
        } else {
            // Something went really wrong, we don't know what
            throw new TidalApiException('An unknown error occurred.', $status);
        }
    }

    /**
     * Make a request to the "auth" endpoint.
     *
     * @api
     *
     * @param  string  $method  The HTTP method to use.
     * @param  string  $uri  The URI to request.
     * @param  string|array  $parameters  Optional. Query string parameters or HTTP body, depending on $method.
     * @param  array  $headers  Optional. HTTP headers.
     *
     * @return array Response data.
     *               - array|object body The response body. Type is controlled by the `return_assoc` option.
     *               - array headers Response headers.
     *               - int status HTTP status code.
     *               - string url The requested URL.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function auth(string $method, string $uri, string|array $parameters = [], array $headers = []): array
    {
        return $this->send($method, self::AUTH_URL . $uri, $parameters, $headers);
    }

    /**
     * Make a request to the "api" endpoint.
     *
     * @api
     *
     * @param  string  $method  The HTTP method to use.
     * @param  string  $uri  The URI to request.
     * @param  string|array  $parameters  Optional. Query string parameters or HTTP body, depending on $method.
     * @param  array  $headers  Optional. HTTP headers.
     *
     * @return array Response data.
     *               - array|object body The response body. Type is controlled by the `return_assoc` option.
     *               - array headers Response headers.
     *               - int status HTTP status code.
     *               - string url The requested URL.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function api(string $method, string $uri, string|array $parameters = [], array $headers = []): array
    {
        return $this->send($method, self::API_URL . $uri, $parameters, $headers);
    }

    /**
     * Get the latest full response from the Tidal Api.
     *
     * @api
     *
     * @return array Response data.
     *               - array|object body The response body. Type is controlled by the `return_assoc` option.
     *               - array headers Response headers.
     *               - int status HTTP status code.
     *               - string url The requested URL.
     */
    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }

    /**
     * Set options
     *
     * @api
     *
     * @param  array|object  $options  Options to set.
     */
    public function setOptions(array|object $options): self
    {
        $this->options = array_merge($this->options, (array) $options);

        return $this;
    }
}
