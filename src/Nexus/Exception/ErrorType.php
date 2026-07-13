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
 * Nexus handler-error wire types. `Unknown` is a local fallback for an unrecognized incoming
 * type (never emitted on the wire); every other case is a spec-defined value.
 *
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
}
