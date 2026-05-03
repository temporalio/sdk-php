<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * Discriminated DTO carrying the outcome of an `ExecuteNexusOperation` start
 * request from RoadRunner back to PHP. Encoded by RR via the Temporal data
 * converter (JSON), decoded on the PHP side via
 * `EncodedValues::getValue(0, NexusStartEnvelope::class)`.
 *
 * - {@see self::$async} `true` → operation is in flight; {@see self::$token}
 *   carries the server-issued operation token. The actual result will arrive
 *   via subsequent {@see \Temporal\Internal\Transport\Request\GetNexusOperationResult} polls.
 * - {@see self::$async} `false` → operation completed inline (sync). The
 *   result payload follows as `Payloads[1]` of the same response.
 *
 * @internal
 */
final class NexusStartEnvelope
{
    #[Marshal(name: 'async')]
    public bool $async = false;

    #[Marshal(name: 'token')]
    public string $token = '';
}
