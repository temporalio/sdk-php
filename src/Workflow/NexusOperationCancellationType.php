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
    case Unspecified = 0;

    /**
     * Do not request cancellation of the operation. The handler workflow
     * keeps running; the caller workflow continues to await its natural
     * completion (the SDK suppresses the wire-level cancel command, so the
     * server is never notified of the caller's cancel request).
     *
     * Note: only the cancel-suppression part is implemented. The Java/Go
     * semantics also let the caller resume *immediately* after the local
     * cancel without awaiting the handler — that requires plumbing through
     * `Internal\Workflow\Process\Scope::onRequest` and is not yet supported.
     */
    case Abandon = 1;
    case TryCancel = 2;
    case WaitRequested = 3;
    case WaitCompleted = 4;
}
