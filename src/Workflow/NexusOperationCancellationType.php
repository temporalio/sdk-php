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
    case Abandon = self::ABANDON;
    case TryCancel = self::TRY_CANCEL;
    case WaitRequested = self::WAIT_REQUESTED;
    case WaitCompleted = self::WAIT_COMPLETED;

    // Mirror as int constants — case values can't reference siblings during init.
    public const UNSPECIFIED = 0;
    public const ABANDON = 1;
    public const TRY_CANCEL = 2;
    public const WAIT_REQUESTED = 3;
    public const WAIT_COMPLETED = 4;
}
