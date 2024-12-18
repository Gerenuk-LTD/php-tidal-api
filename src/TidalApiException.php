<?php

declare(strict_types=1);

namespace TidalApi;

use Exception;

class TidalApiException extends Exception
{
    public const TOKEN_EXPIRED = 'The access token expired';

    public const RATE_LIMIT_STATUS = 429;

    /**
     * The reason string from a player request's error object.
     */
    private string $reason = '';

    /**
     * Returns the reason string from a player request's error object.
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Set the reason string.
     */
    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    /**
     * Returns whether the exception was thrown because of an expired access token.
     */
    public function hasExpiredToken(): bool
    {
        return $this->getMessage() === self::TOKEN_EXPIRED;
    }

    /**
     * Returns whether the exception was thrown because of rate limiting.
     */
    public function isRateLimited(): bool
    {
        return $this->getCode() === self::RATE_LIMIT_STATUS;
    }
}
