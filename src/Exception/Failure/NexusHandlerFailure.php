<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

/**
 * Typed exception for a Nexus HandlerError carried over the wire as
 * {@see \Temporal\Api\Failure\V1\NexusHandlerFailureInfo}.
 *
 * Transport-level error raised by the Nexus handler itself (`BAD_REQUEST`,
 * `INTERNAL`, `NOT_FOUND`, etc. — see the Nexus spec). Maps 1:1 to the
 * `NexusHandlerException` thrown on the server side.
 */
class NexusHandlerFailure extends TemporalFailure
{
    public function __construct(
        string $message,
        private readonly string $type,
        private readonly int $retryBehavior,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, null, $previous);
    }

    /**
     * Raw Nexus error-type string (e.g. `BAD_REQUEST`, `INTERNAL`, or a
     * user-defined one). Preserved verbatim so unknown types survive
     * round-trip.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@see \Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior} enum value.
     */
    public function getRetryBehavior(): int
    {
        return $this->retryBehavior;
    }
}
