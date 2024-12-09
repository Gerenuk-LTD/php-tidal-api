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
     * Set options
     *
     * @param  array|object  $options  Options to set.
     */
    public function setOptions(array|object $options): self
    {
        $this->options = array_merge($this->options, (array) $options);

        return $this;
    }

    /**
     * Set the Session object to use.
     *
     * @param  ?Session  $session  The Session object.
     */
    public function setSession(?Session $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Set the access token to use.
     *
     * @param  string  $accessToken  The access token.
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Send a request to the Tidal Api, automatically refreshing the access token as needed.
     *
     * @param  string  $method  The HTTP method to use.
     * @param  string  $uri  The URI to request.
     * @param  string|array  $parameters  Optional. Query string parameters or HTTP body, depending on $method.
     * @param  array  $headers  Optional. HTTP headers.
     * @return array Response data.
     *               - array|object body The response body. Type is controlled by the `return_assoc` option.
     *               - array headers Response headers.
     *               - int status HTTP status code.
     *               - string url The requested URL.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    protected function sendRequest(
        string $method,
        string $uri,
        string|array $parameters = [],
        array $headers = [],
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

                if (! $result) {
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
     * Add authorization headers.
     *
     * @param  $headers  array. Optional. Additional headers to merge with the authorization headers.
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
     * Get all album details by available filters.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-albums
     *
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the album.
     *                          - array include Optional. Customise related resource to be returned.
     * @return array|object All album details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getAlbums(string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/albums/';

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get album details by a unique id.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-album
     *
     * @param  string  $albumId  Id of the album.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the album.
     *                          - array include Optional. Customise related resource to be returned.
     * @return array|object The requested album's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getAlbum(string $albumId, string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/albums/' . $albumId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an album relationship.
     *
     * @param  string  $albumId  Id of the album.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the album.
     * @return array|object The requested album relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getAlbumRelationship(
        string $albumId,
        string $countryCode,
        string $relationship,
        array $options = [],
    ): array|object {
        $uri = '/v2/albums/' . $albumId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get artists relationship details of the related album resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-artists-relationship
     *
     * @param  string  $albumId  Id of the album.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the album.
     * @return array|object The requested album's artist relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getAlbumRelationshipArtists(
        string $albumId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getAlbumRelationship($albumId, $countryCode, 'artists', $options);
    }

    /**
     * Get items relationship details of the related album resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-items-relationship
     *
     * @param  string  $albumId  Id of the album.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the album.
     * @return array|object The requested album's items relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getAlbumRelationshipItems(
        string $albumId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getAlbumRelationship($albumId, $countryCode, 'items', $options);
    }

    /**
     * Get providers relationship details of the related album resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-providers-relationship
     *
     * @param  string  $albumId  Id of the album.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the album.
     * @return array|object The requested album's providers relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getAlbumRelationshipProviders(
        string $albumId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getAlbumRelationship($albumId, $countryCode, 'providers', $options);
    }

    /**
     * Get similarAlbums relationship details of the related album resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-similaralbums-relationship
     *
     * @param  string  $albumId  Id of the album.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the album.
     * @return array|object The requested album's similarAlbums relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getAlbumRelationshipSimilarAlbums(
        string $albumId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getAlbumRelationship($albumId, $countryCode, 'similarAlbums', $options);
    }

    /**
     * Get all artistRole details by available filters.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-artistroles
     *
     * @param  array  $options  Optional. Options for the album.
     * @return array|object All artistRole details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRoles(array $options = []): array|object
    {
        $uri = '/v2/artistsRoles/';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artistRole details by a unique id.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artistrolesid
     *
     * @param  array  $options  Optional. Options for the album.
     * @return array|object The requested artistRole's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRole(string $artistRoleId, array $options = []): array|object
    {
        $uri = '/v2/artistsRoles/' . $artistRoleId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get all artist details by available filters.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-artists
     *
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     *                          - array include Optional. Customise related resource to be returned.
     * @return array|object All artist details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getArtists(string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/artists/';

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist details by a unique id.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-artist
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     *                          - array include Optional. Customise related resource to be returned.
     * @return array|object The requested artist's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getArtist(string $artistId, string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/artists/' . $artistId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist relationship.
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationship(
        string $artistId,
        string $countryCode,
        string $relationship,
        array $options = [],
    ): array|object {
        $uri = '/v2/artists/' . $artistId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get albums relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-albums-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's albums relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipAlbums(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'albums', $options);
    }

    /**
     * Get radio relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-radio-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's radio relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipRadio(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'radio', $options);
    }

    /**
     * Get roles relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-roles-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's roles relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipRoles(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'roles', $options);
    }

    /**
     * Get similarArtists relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-similarartists-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's similarArtists relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipSimilarArtists(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'similarArtists', $options);
    }

    /**
     * Get trackProviders relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-trackproviders-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's trackProviders relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipTrackProviders(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'trackProviders', $options);
    }

    /**
     * Get tracks relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-tracks-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's tracks relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipTracks(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'tracks', $options);
    }

    /**
     * Get videos relationship details of the related artist resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-videos-relationship
     *
     * @param  string  $artistId  Id of the artist.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the artist.
     * @return array|object The requested artist's videos relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getArtistRelationshipVideos(
        string $artistId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getArtistRelationship($artistId, $countryCode, 'videos', $options);
    }

    /**
     * Get all provider details by available filters.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-providers
     *
     * @param  array  $options  Optional. Options for the provider.
     * @return array|object All provider details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getProviders(array $options = []): array|object
    {
        $uri = '/v2/providers/';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get provider details by a unique id.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-providersid
     *
     * @param  string  $providerId  Id of the provider.
     * @param  array  $options  Optional. Options for the provider.
     * @return array|object The requested provider's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getProvider(string $providerId, array $options = []): array|object
    {
        $uri = '/v2/providers/' . $providerId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get all track details by available filters.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-tracks
     *
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object All track details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTracks(string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/tracks/';

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get track details by a unique id.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-track
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested track's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrack(string $trackId, string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/tracks/' . $trackId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a track relationship.
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested track relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrackRelationship(
        string $trackId,
        string $countryCode,
        string $relationship,
        array $options = [],
    ): array|object {
        $uri = '/v2/tracks/' . $trackId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get albums relationship details of the related track resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-albums-relationship
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The request track's albums relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrackRelationshipAlbums(
        string $trackId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getTrackRelationship($trackId, $countryCode, 'albums', $options);
    }

    /**
     * Get artists relationship details of the related track resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-artists-relationship
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested track's artists relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrackRelationshipArtists(
        string $trackId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getTrackRelationship($trackId, $countryCode, 'artists', $options);
    }

    /**
     * Get providers relationship details of the related track resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-providers-relationship
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested track's providers relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrackRelationshipProviders(
        string $trackId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getTrackRelationship($trackId, $countryCode, 'providers', $options);
    }

    /**
     * Get radio relationship details of the related track resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-radio-relationship
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested track's radio relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrackRelationshipRadio(
        string $trackId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getTrackRelationship($trackId, $countryCode, 'radio', $options);
    }

    /**
     * Get similarTracks relationship details of the related track resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-similartracks-relationship
     *
     * @param  string  $trackId  Id of the track.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested track's similarTracks relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getTrackRelationshipSimilarTracks(
        string $trackId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getTrackRelationship($trackId, $countryCode, 'similarTracks', $options);
    }

    /**
     * Get all video details by available filters.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-videos
     *
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object All video details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getVideos(string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/videos/';

        $options = array_merge([
            'countryCode' => $countryCode,
        ], (array) $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get video details by a unique id.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-video
     *
     * @param  string  $videoId  Id of the video.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the track.
     * @return array|object The requested video's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getVideo(string $videoId, string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/videos/' . $videoId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a video relationship.
     *
     * @param  string  $videoId  Id of the video.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested video relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getVideoRelationship(
        string $videoId,
        string $countryCode,
        string $relationship,
        array $options = [],
    ): array|object {
        $uri = '/v2/videos/' . $videoId . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get albums relationship details of the related video resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-video-albums-relationship
     *
     * @param  string  $videoId  Id of the video.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested video's albums relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getVideoRelationshipAlbums(
        string $videoId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getVideoRelationship($videoId, $countryCode, 'albums', $options);
    }

    /**
     * Get artists relationship details of the related video resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-video-artists-relationship
     *
     * @param  string  $videoId  Id of the video.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested video's artists relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getVideoRelationshipArtists(
        string $videoId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getVideoRelationship($videoId, $countryCode, 'artists', $options);
    }

    /**
     * Get providers relationship details of the related video resource.
     * https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-video-providers-relationship
     *
     * @param  string  $videoId  Id of the video.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested video's providers relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getVideoRelationshipProviders(
        string $videoId,
        string $countryCode,
        array $options = [],
    ): array|object {
        return $this->getVideoRelationship($videoId, $countryCode, 'providers', $options);
    }

    /**
     * Get search results for music: albums, artists, tracks, etc.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested search results. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function search(string $query, string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/searchresults/' . $query;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a search relationship.
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested search relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationship(string $query, string $countryCode, string $relationship, array $options = []): array|object
    {
        $uri = '/v2/searchresults/' . $query . '/relationships/' . $relationship;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get search results for album by a query.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-albums-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the search.
     * @return array|object The requested searches albums relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationshipAlbums(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'albums', $options);
    }

    /**
     * Get search results for artists by a query.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-artists-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the search.
     * @return array|object The requested searches artists relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationshipArtists(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'artists', $options);
    }

    /**
     * Get search results for playlists by a query.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-playlists-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the search.
     * @return array|object The requested searches playlists relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationshipPlaylists(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'playlists', $options);
    }

    /**
     * Get search results for top hits by a query: artists, albums, tracks, videos.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-tophits-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the search.
     * @return array|object The requested searches topHits relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationshipTopHits(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'topHits', $options);
    }

    /**
     * Get search results for tracks by a query.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-tracks-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the search.
     * @return array|object The requested searches tracks relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationshipTracks(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'tracks', $options);
    }

    /**
     * Get search results for videos by a query.
     * https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-videos-v2
     *
     * @param  string  $query  The query to search for.
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code.
     * @param  array  $options  Optional. Options for the search.
     * @return array|object The requested searches videos relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getSearchRelationshipVideos(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'videos', $options);
    }

    /**
     * Get users by id.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-users-v2
     *
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested users' details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getUsers(array $options = []): array|object
    {
        $uri = '/v2/users';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get the currently authenticated user.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-me-v2
     *
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The currently authenticated user's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function me(array $options = []): array|object
    {
        $uri = '/v2/users/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user by id.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested user's details. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUser(string $userId, array $options = []): array|object
    {
        $uri = '/v2/users/' . $userId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user relationship.
     *
     * @param  string  $userId  Id of the user.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested user relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRelationship(string $userId, string $relationship, array $options = []): array|object
    {
        $uri = '/v2/users/' . $userId . '/relationships/' . $relationship;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a users entitlements relationship.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-userentitlements-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested users entitlements relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRelationshipEntitlements(string $userId, array $options = []): array|object
    {
        return $this->getUserRelationship($userId, 'entitlements', $options);
    }

    /**
     * Get a users public profile relationship.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-userprofile-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  string  $locale  The locale.
     * @param  array  $options  Optional. Options for the video.
     * @return array|object The requested users public profile relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRelationshipPublicProfile(
        string $userId,
        string $locale,
        array $options = [],
    ): array|object {
        $options = array_merge([
            'locale' => $locale,
        ], $options);

        return $this->getUserRelationship($userId, 'publicProfile', $options);
    }

    /**
     * Get a users recommendations relationship.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-userrecommendations-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested users recommendations relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRelationshipRecommendations(string $userId, array $options = []): array|object
    {
        return $this->getUserRelationship($userId, 'recommendations', $options);
    }

    /**
     * Get the currently authenticated user's entitlements.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-myuserentitlement-v2
     *
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The currently authenticated users entitlements. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getMyEntitlements(array $options = []): array|object
    {
        $uri = '/v2/userEntitlements/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's entitlements.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-userentitlement-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested users entitlements. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserEntitlements(string $userId, array $options = []): array|object
    {
        $uri = '/v2/userEntitlements/' . $userId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's user recommendations in batch.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-batch-v2
     *
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested users recommendations in batch. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRecommendationsBatch(array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get the currently authenticated users recommendations.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-myrecommendations-v2
     *
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The currently authenticated users recommendations. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getMyRecommendations(array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's user recommendations.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-batch-v2
     *
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested users user recommendations. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRecommendations(string $userId, array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/' . $userId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a userRecommendations relationship.
     *
     * @param  string  $userId  Id of the user.
     * @param  string  $relationship  Relationship to return.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested userRecommendations relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRecommendationRelationship(string $userId, string $relationship, array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/' . $userId . '/relationships/' . $relationship;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a userRecommendation discoveryMixes relationship.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-discoverymixes-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested userRecommendation discoveryMixes relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRecommendationRelationshipDiscoveryMixes(string $userId, array $options = []): array|object
    {
        return $this->getUserRecommendationRelationship($userId, 'discoveryMixes', $options);
    }

    /**
     * Get a userRecommendation myMixes relationship.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-mymixes-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested userRecommendation myMixes relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRecommendationRelationshipMyMixes(string $userId, array $options = []): array|object
    {
        return $this->getUserRecommendationRelationship($userId, 'myMixes', $options);
    }

    /**
     * Get a userRecommendation newArrivalMixes relationship.
     * https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-newarrivalmixes-v2
     *
     * @param  string  $userId  Id of the user.
     * @param  array  $options  Optional. Options for the user.
     * @return array|object The requested userRecommendation newArrivalMixes relationship. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiException
     * @throws TidalApiAuthException
     */
    public function getUserRecommendationRelationshipNewArrivalMixes(string $userId, array $options = []): array|object
    {
        return $this->getUserRecommendationRelationship($userId, 'newArrivalMixes', $options);
    }

    /**
     * Get the currently authenticated users playlists.
     * https://developer.tidal.com/apiref?spec=user-playlist-v2&ref=get-my-playlists-v2
     *
     * @param  array  $options  Optional. Options for the users playlists.
     * @return array|object The currently authenticated users playlists. Type is controlled by the `return_assoc` option.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getMyPlaylists(array $options = []): array|object
    {
        $uri = '/v2/playlists/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Convert an array to a comma-separated string. If it's already a string, do nothing.
     *
     * @param  array|string  $value  The value to convert.
     * @return string A comma-separated string.
     */
    protected function toCommaString(string|array $value): string
    {
        if (is_array($value)) {
            return implode(',', $value);
        }

        return $value;
    }
}
