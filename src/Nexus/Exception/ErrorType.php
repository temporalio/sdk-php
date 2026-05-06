<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

/**
 * @see https://github.com/nexus-rpc/api/blob/main/SPEC.md
 */
enum ErrorType: string
{
    case Unknown = 'UNKNOWN';
    case BadRequest = 'BAD_REQUEST';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Unauthorized = 'UNAUTHORIZED';
    case NotFound = 'NOT_FOUND';
    case Conflict = 'CONFLICT';
    case RequestTimeout = 'REQUEST_TIMEOUT';
    case ResourceExhausted = 'RESOURCE_EXHAUSTED';
    case Internal = 'INTERNAL';
    case NotImplemented = 'NOT_IMPLEMENTED';
    case Unavailable = 'UNAVAILABLE';
    case UpstreamTimeout = 'UPSTREAM_TIMEOUT';

    /**
     * Inverse of {@see self::httpStatus()}. Codes not in the spec table —
     * including ad-hoc 4xx/5xx values — resolve to {@see self::Unknown} so
     * the wire signal is preserved without inventing a category.
     */
    public static function fromHttpStatus(int $status): self
    {
        return match ($status) {
            400 => self::BadRequest,
            401 => self::Unauthenticated,
            403 => self::Unauthorized,
            404 => self::NotFound,
            408 => self::RequestTimeout,
            409 => self::Conflict,
            429 => self::ResourceExhausted,
            500 => self::Internal,
            501 => self::NotImplemented,
            503 => self::Unavailable,
            520 => self::UpstreamTimeout,
            default => self::Unknown,
        };
    }

    /**
     * Canonical HTTP status code per Nexus spec.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::BadRequest        => 400,
            self::Unauthenticated   => 401,
            self::Unauthorized      => 403,
            self::NotFound          => 404,
            self::RequestTimeout    => 408,
            self::Conflict          => 409,
            self::ResourceExhausted => 429,
            self::Internal,
            self::Unknown           => 500,
            self::NotImplemented    => 501,
            self::Unavailable       => 503,
            self::UpstreamTimeout   => 520,
        };
    }
}
