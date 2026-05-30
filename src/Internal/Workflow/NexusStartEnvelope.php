<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * Discriminated DTO carrying the outcome of a Nexus start handshake from
 * RoadRunner back to PHP. Pushed by RR's `nexusStartedRegistry` listener in
 * response to {@see \Temporal\Internal\Transport\Request\GetNexusOperationStarted}.
 * Encoded via the Temporal data converter (JSON), decoded on the PHP side via
 * `EncodedValues::getValue(0, NexusStartEnvelope::class)`.
 *
 * - {@see self::$async} `true` → handler ack'd as long-running; {@see self::$token}
 *   carries the server-issued operation token. The result arrives later on the
 *   completion-paired `ExecuteNexusOperation` response.
 * - {@see self::$async} `false` → operation completed inline (sync); token is
 *   empty. The result still arrives on the same completion-paired response.
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
