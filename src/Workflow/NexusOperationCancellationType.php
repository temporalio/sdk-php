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
    case Unspecified = self::UNSPECIFIED;

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
    case Abandon = self::ABANDON;
    case TryCancel = self::TRY_CANCEL;
    case WaitRequested = self::WAIT_REQUESTED;
    case WaitCompleted = self::WAIT_COMPLETED;

    // Int mirrors used in property defaults — `EnumCase->value` in const-expr requires PHP 8.2+, project targets 8.1.
    public const UNSPECIFIED = 0;
    public const ABANDON = 1;
    public const TRY_CANCEL = 2;
    public const WAIT_REQUESTED = 3;
    public const WAIT_COMPLETED = 4;
}
