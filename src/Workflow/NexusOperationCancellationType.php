<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

/**
 * Action for a Nexus operation when the caller is cancelled.
 * Values match sdk-go `NexusOperationCancellationType` (iota-ordered).
 */
enum NexusOperationCancellationType: int
{
    /**
     * No cancellation type was specified. The server treats this as
     * {@see self::WaitCompleted}, matching the sdk-go default.
     */
    case Unspecified = 0;

    /**
     * Do not request cancellation of the operation. The handler workflow
     * keeps running server-side; the SDK suppresses the wire-level cancel
     * command, so the server is never notified of the caller's cancel
     * request, and the caller's future resolves immediately with a
     * {@see \Temporal\Exception\Failure\CanceledFailure} (surfaced as a
     * {@see \Temporal\Exception\Failure\NexusOperationFailure}).
     */
    case Abandon = 1;
    case TryCancel = 2;
    case WaitRequested = 3;
    case WaitCompleted = 4;
}
