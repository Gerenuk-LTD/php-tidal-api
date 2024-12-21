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
     * Create a new TidalApi instance.
     */
    public function __construct(array|object $options = [], ?Session $session = null, ?Request $request = null)
    {
        $this->setOptions($options);
        $this->setSession($session);

        $this->request = $request ?? new Request();
    }

    /**
     * Set the options for the request.
     *
     * @return $this
     */
    public function setOptions(array|object $options): self
    {
        $this->options = array_merge($this->options, (array) $options);

        return $this;
    }

    /**
     * Set the session object to be used.
     *
     * @return $this
     *
     * @api
     */
    public function setSession(?Session $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Set the access token to be used.
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
     * Send a request to the Tidal Api, automatically refreshing the access token as needed.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-albums
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-album
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-artists-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-items-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-providers-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-album-similaralbums-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-artistroles
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getArtistRoles(array $options = []): array|object
    {
        $uri = '/v2/artistsRoles/';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artistRole details by a unique id.
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artistrolesid
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getArtistRole(string $artistRoleId, array $options = []): array|object
    {
        $uri = '/v2/artistsRoles/' . $artistRoleId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get all artist details by available filters.
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-artists
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-artist
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-albums-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-radio-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-roles-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-similarartists-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-trackproviders-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-tracks-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-artist-videos-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-providers
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getProviders(array $options = []): array|object
    {
        $uri = '/v2/providers/';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get provider details by a unique id.
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-providersid
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getProvider(string $providerId, array $options = []): array|object
    {
        $uri = '/v2/providers/' . $providerId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get all track details by available filters.
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-tracks
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-track
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-albums-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-artists-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-providers-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-radio-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-track-similartracks-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-all-videos
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getVideos(string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/videos/';

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get video details by a unique id.
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-single-video
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-video-albums-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-video-artists-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=catalogue-v2&ref=get-video-providers-relationship
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
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
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-albums-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getSearchRelationshipAlbums(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'albums', $options);
    }

    /**
     * Get search results for artists by a query.
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-artists-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getSearchRelationshipArtists(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'artists', $options);
    }

    /**
     * Get search results for playlists by a query.
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-playlists-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getSearchRelationshipPlaylists(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'playlists', $options);
    }

    /**
     * Get search results for top hits by a query: artists, albums, tracks, videos.
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-tophits-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getSearchRelationshipTopHits(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'topHits', $options);
    }

    /**
     * Get search results for tracks by a query.
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-tracks-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getSearchRelationshipTracks(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'tracks', $options);
    }

    /**
     * Get search results for videos by a query.
     *
     * @link https://developer.tidal.com/apiref?spec=search-v2&ref=get-searchresults-relationship-videos-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getSearchRelationshipVideos(string $query, string $countryCode, array $options = []): array|object
    {
        return $this->getSearchRelationship($query, $countryCode, 'videos', $options);
    }

    /**
     * Get users by id.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-users-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUsers(array $options = []): array|object
    {
        $uri = '/v2/users';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get the currently authenticated user.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-me-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function me(array $options = []): array|object
    {
        $uri = '/v2/users/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user by id.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getUserRelationship(string $userId, string $relationship, array $options = []): array|object
    {
        $uri = '/v2/users/' . $userId . '/relationships/' . $relationship;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a users entitlements relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-userentitlements-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserRelationshipEntitlements(string $userId, array $options = []): array|object
    {
        return $this->getUserRelationship($userId, 'entitlements', $options);
    }

    /**
     * Get a users public profile relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-userprofile-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-user-userrecommendations-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserRelationshipRecommendations(string $userId, array $options = []): array|object
    {
        return $this->getUserRelationship($userId, 'recommendations', $options);
    }

    /**
     * Get the currently authenticated user's entitlements.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-myuserentitlement-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getMyEntitlements(array $options = []): array|object
    {
        $uri = '/v2/userEntitlements/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's entitlements.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-userentitlement-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserEntitlements(string $userId, array $options = []): array|object
    {
        $uri = '/v2/userEntitlements/' . $userId;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's user recommendations in batch.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-batch-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserRecommendationsBatch(array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get the currently authenticated users recommendations.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-myrecommendations-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getMyRecommendations(array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's user recommendations.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-batch-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
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
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getUserRecommendationRelationship(string $userId, string $relationship, array $options = []): array|object
    {
        $uri = '/v2/userRecommendations/' . $userId . '/relationships/' . $relationship;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a userRecommendation discoveryMixes relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-discoverymixes-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserRecommendationRelationshipDiscoveryMixes(string $userId, array $options = []): array|object
    {
        return $this->getUserRecommendationRelationship($userId, 'discoveryMixes', $options);
    }

    /**
     * Get a userRecommendation myMixes relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-mymixes-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserRecommendationRelationshipMyMixes(string $userId, array $options = []): array|object
    {
        return $this->getUserRecommendationRelationship($userId, 'myMixes', $options);
    }

    /**
     * Get a userRecommendation newArrivalMixes relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-v2&ref=get-userrecommendations-newarrivalmixes-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getUserRecommendationRelationshipNewArrivalMixes(string $userId, array $options = []): array|object
    {
        return $this->getUserRecommendationRelationship($userId, 'newArrivalMixes', $options);
    }

    /**
     * Get user playlists.
     *
     * @link https://developer.tidal.com/apiref?spec=user-playlist-v2&ref=get-playlists-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getPlaylists(string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/playlists';

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get the currently authenticated users playlists.
     *
     * @link https://developer.tidal.com/apiref?spec=user-playlist-v2&ref=get-my-playlists-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getMyPlaylists(array $options = []): array|object
    {
        $uri = '/v2/playlists/me';

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get playlist details by a unique id.
     *
     * @link https://developer.tidal.com/apiref?spec=user-playlist-v2&ref=get-playlist-by-id-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getPlaylist(string $playlistId, string $countryCode, array $options = []): array|object
    {
        $uri = '/v2/playlists/' . $playlistId;

        $options = array_merge([
            'countryCode' => $countryCode,
        ], $options);

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a playlist relationship.
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     */
    public function getPlaylistRelationship(string $playlistId, string $relationship, array $options = []): array|object
    {
        $uri = '/v2/playlists/' . $playlistId . '/relationships/' . $relationship;

        $this->lastResponse = $this->sendRequest('GET', $uri, $options);

        return $this->lastResponse['body'];
    }

    /**
     * Get a playlists items relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-playlist-v2&ref=get-playlist-items-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getPlaylistRelationshipItems(string $playlistId, array $options = []): array|object
    {
        return $this->getPlaylistRelationship($playlistId, 'items', $options);
    }

    /**
     * Get a playlists owners relationship.
     *
     * @link https://developer.tidal.com/apiref?spec=user-playlist-v2&ref=get-playlist-owner-v2
     *
     * @throws TidalApiAuthException
     * @throws TidalApiException
     *
     * @api
     */
    public function getPlaylistRelationshipOwners(string $playlistId, array $options = []): array|object
    {
        return $this->getPlaylistRelationship($playlistId, 'owners', $options);
    }

    /**
     * Convert an array to a comma-separated string. If it's already a string, do nothing.
     */
    protected function toCommaString(string|array $value): string
    {
        return is_array($value) ? implode(',', $value) : $value;
    }
}
