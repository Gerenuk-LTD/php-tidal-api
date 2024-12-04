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
     * Get an album.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-v2&at=THIRD_PARTY
     *
     * @param string $albumId Id of the album.
     * @param string $countryCode ISO 3166-1 alpha-2 country code.
     * @param array|object $options Optional. Options for the album.
     * - array include Optional. Customise related resource to be returned.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     *
     * @return array|object The requested album. Type is controlled by the `return_assoc` option.
     */
    public function getAlbum(string $albumId, string $countryCode, array|object $options = []): array|object
    {
        $uri = '/v2/albums/' . $albumId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an album relationship.
     *
     * @param string $albumId Id of the album.
     * @param string $countryCode ISO 3166-1 alpha-2 country code.
     * @param string $relationship Relationship to return.
     * @param array|object $options Optional. Options for the album.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The requested album. Type is controlled by the `return_assoc` option.
     */
    public function getAlbumRelationship(string $albumId, string $countryCode, string $relationship, array|object $options = []): array|object
    {
        $uri = '/v2/albums/' . $albumId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an albums artists relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-artists-v2&at=THIRD_PARTY
     *
     * @param  string  $albumId Id of the album.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the album.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The requested album relationship. Type is controlled by the `return_assoc` option.
     */
    public function getAlbumRelationshipArtists(string $albumId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getAlbumRelationship($albumId, $countryCode, 'artists', $options);
    }

    /**
     * Get an albums items relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-items-v2&at=THIRD_PARTY
     *
     * @param  string  $albumId Id of the album.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the album.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The album items relationship. Type is controlled by the `return_assoc` option.
     */
    public function getAlbumRelationshipItems(string $albumId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getAlbumRelationship($albumId, $countryCode, 'items', $options);
    }

    /**
     * Get an albums providers relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-providers-v2&at=THIRD_PARTY
     *
     * @param  string  $albumId Id of the album.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the album.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The album providers relationship. Type is controlled by the `return_assoc` option.
     */
    public function getAlbumRelationshipProviders(string $albumId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getAlbumRelationship($albumId, $countryCode, 'providers', $options);
    }

    /**
     * Get an albums similar albums relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-similar-albums-v2&at=THIRD_PARTY
     *
     * @param  string  $albumId Id of the album.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the album.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The album similar albums' relationship. Type is controlled by the `return_assoc` option.
     */
    public function getAlbumRelationshipSimilarAlbums(string $albumId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getAlbumRelationship($albumId, $countryCode, 'similarAlbums', $options);
    }

    /**
     * Get an artist.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-v2&at=THIRD_PARTY
     *
     * @param string $artistId Id of the artist.
     * @param string $countryCode ISO 3166-1 alpha-2 country code.
     * @param array|object $options Optional. Options for the artist.
     * - array include Optional. Customise related resource to be returned.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     *
     * @return array|object The requested artist. Type is controlled by the `return_assoc` option.
     */
    public function getArtist(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        $uri = '/v2/artists/' . $artistId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist relationship.
     *
     * @param string $artistId Id of the artist.
     * @param string $countryCode ISO 3166-1 alpha-2 country code.
     * @param string $relationship Relationship to return.
     * @param array|object $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The requested album. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationship(string $artistId, string $countryCode, string $relationship, array|object $options = []): array|object
    {
        $uri = '/v2/artists/' . $artistId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artists albums relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-albums-v2&at=THIRD_PARTY
     *
     * @param  string  $artistId Id of the artist.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The artist albums relationship. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationshipAlbums(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getArtistRelationship($artistId, $countryCode, 'albums', $options);
    }

    /**
     * Get an artists radio relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-radio-v2&at=THIRD_PARTY
     *
     * @param  string  $artistId Id of the artist.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The artist albums relationship. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationshipRadio(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getArtistRelationship($artistId, $countryCode, 'radio', $options);
    }

    /**
     * Get an artists similar artists relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-similar-artists-v2&at=THIRD_PARTY
     *
     * @param  string  $artistId Id of the artist.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The artist similar artists' relationship. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationshipSimilarArtists(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getArtistRelationship($artistId, $countryCode, 'similarArtists', $options);
    }

    /**
     * Get an artists track providers relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-track-providers-v2&at=THIRD_PARTY
     *
     * @param  string  $artistId Id of the artist.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The artist track providers relationship. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationshipTrackProviders(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getArtistRelationship($artistId, $countryCode, 'providers', $options);
    }

    /**
     * Get an artists tracks relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-tracks-v2&at=THIRD_PARTY
     *
     * @param  string  $artistId Id of the artist.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The artist tracks relationship. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationshipTracks(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getArtistRelationship($artistId, $countryCode, 'tracks', $options);
    }

    /**
     * Get an artists videos relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-videos-v2&at=THIRD_PARTY
     *
     * @param  string  $artistId Id of the artist.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the artist.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The artist videos relationship. Type is controlled by the `return_assoc` option.
     */
    public function getArtistRelationshipVideos(string $artistId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getArtistRelationship($artistId, $countryCode, 'videos', $options);
    }

    /**
     * Get a track.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-videos-v2&at=THIRD_PARTY
     *
     * @param  string  $trackId Id of the track.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The requested track. Type is controlled by the `return_assoc` option.
     */
    public function getTrack(string $trackId, string $countryCode, array|object $options = []): array|object
    {
        $uri = '/v2/tracks/' . $trackId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a track relationship.
     *
     * @param string $trackId Id of the track.
     * @param string $countryCode ISO 3166-1 alpha-2 country code.
     * @param string $relationship Relationship to return.
     * @param array|object $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The requested track relationship. Type is controlled by the `return_assoc` option.
     */
    public function getTrackRelationship(string $trackId, string $countryCode, string $relationship, array|object $options = []): array|object
    {
        $uri = '/v2/tracks/' . $trackId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a tracks albums relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-albums-v2&at=THIRD_PARTY_PROD
     *
     * @param  string  $trackId Id of the track.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The track albums relationship. Type is controlled by the `return_assoc` option.
     */
    public function getTrackRelationshipAlbums(string $trackId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getTrackRelationship($trackId, $countryCode, 'albums', $options);
    }

    /**
     * Get a tracks artists relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-artists-v2&at=THIRD_PARTY_PROD
     *
     * @param  string  $trackId Id of the track.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The track artists relationship. Type is controlled by the `return_assoc` option.
     */
    public function getTrackRelationshipArtists(string $trackId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getTrackRelationship($trackId, $countryCode, 'artists', $options);
    }

    /**
     * Get a tracks providers relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-providers-v2&at=THIRD_PARTY_PROD
     *
     * @param  string  $trackId Id of the track.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The track providers relationship. Type is controlled by the `return_assoc` option.
     */
    public function getTrackRelationshipProviders(string $trackId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getTrackRelationship($trackId, $countryCode, 'providers', $options);
    }

    /**
     * Get a tracks radio relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-radio-v2&at=THIRD_PARTY_PROD
     *
     * @param  string  $trackId Id of the track.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The track radio relationship. Type is controlled by the `return_assoc` option.
     */
    public function getTrackRelationshipRadio(string $trackId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getTrackRelationship($trackId, $countryCode, 'radio', $options);
    }

    /**
     * Get a tracks similar tracks relationship.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-radio-v2&at=THIRD_PARTY_PROD
     *
     * @param  string  $trackId Id of the track.
     * @param  string  $countryCode ISO 3166-1 alpha-2 country code.
     * @param  array|object  $options Optional. Options for the track.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @return array|object The track similar tracks relationship. Type is controlled by the `return_assoc` option.
     */
    public function getTrackRelationshipSimilarTracks(string $trackId, string $countryCode, array|object $options = []): array|object
    {
        return $this->getTrackRelationship($trackId, $countryCode, 'similarTracks', $options);
    }

    /**
     * Get the currently authenticated user.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-me-v2&at=THIRD_PARTY
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
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
