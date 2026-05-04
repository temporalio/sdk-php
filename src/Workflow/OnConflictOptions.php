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
 * Server-side flags controlling what gets attached to an already-running
 * workflow when {@see \Temporal\Common\WorkflowIdConflictPolicy::UseExisting}
 * resolves a start-conflict by reusing the existing run.
 *
 * Maps 1:1 to {@see \Temporal\Api\Workflow\V1\OnConflictOptions} on the wire;
 * the Nexus completion-callback delivery requires `attachCompletionCallbacks`
 * so retried `StartWorkflow` requests still bind the caller's callback to the
 * existing run.
 *
 * @psalm-immutable
 */
final class OnConflictOptions
{
    public function __construct(
        public readonly bool $attachRequestId = true,
        public readonly bool $attachCompletionCallbacks = true,
        public readonly bool $attachLinks = true,
    ) {}
}
