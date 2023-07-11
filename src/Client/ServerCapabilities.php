<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

final class ServerCapabilities
{
    public function __construct(
        private bool $signalAndQueryHeader = false,
        private bool $internalErrorDifferentiation = false
    ) {
    }

    /**
     * True if signal and query headers are supported.
     */
    public function isSignalAndQueryHeaderSupports(): bool
    {
        return $this->signalAndQueryHeader;
    }

    /**
     * True if internal errors are differentiated from other types of errors for purposes of
     * retrying non-internal errors.
     * When unset/false, clients retry all failures. When true, clients should only retry
     * non-internal errors.
     */
    public function isInternalErrorDifferentiation(): bool
    {
        return $this->internalErrorDifferentiation;
    }
}
