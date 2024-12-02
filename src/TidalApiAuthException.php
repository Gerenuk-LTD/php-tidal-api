<?php

declare(strict_types=1);

namespace TidalApi;

// Extends from SpotifyWebApiException for backwards compatibility
class TidalApiAuthException extends TidalApiException
{
    public const string INVALID_CLIENT = 'Invalid client';
    public const string INVALID_CLIENT_SECRET = 'Invalid client secret';
    public const string INVALID_REFRESH_TOKEN = 'Invalid refresh token';

    /**
     * Returns whether the exception was thrown because of invalid credentials.
     *
     * @return bool
     */
    public function hasInvalidCredentials(): bool
    {
        return in_array($this->getMessage(), [
            self::INVALID_CLIENT,
            self::INVALID_CLIENT_SECRET,
        ]);
    }

    /**
     * Returns whether the exception was thrown because of an invalid refresh token.
     *
     * @return bool
     */
    public function hasInvalidRefreshToken(): bool
    {
        return $this->getMessage() === self::INVALID_REFRESH_TOKEN;
    }
}
