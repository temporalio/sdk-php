<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

/**
 * Server-side flags controlling what gets attached to an already-running
 * workflow when {@see \Temporal\Common\WorkflowIdConflictPolicy::UseExisting}
 * resolves a start-conflict by reusing the existing run.
 *
 * Maps 1:1 to {@see \Temporal\Api\Workflow\V1\OnConflictOptions} on the wire.
 *
 * @internal Used only by the Nexus caller-side path to make retried
 *           StartWorkflow requests bind their callback to the existing run.
 *           Mirrors Go SDK's internal-only `SetOnConflictOptionsOnStartWorkflowOptions`.
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

    /**
     * Canonical value used by the Nexus caller-side path so retried
     * StartWorkflow requests attach the new callback to the existing run.
     */
    public static function forNexusCompletionCallback(): self
    {
        return new self(
            attachRequestId: true,
            attachCompletionCallbacks: true,
            attachLinks: true,
        );
    }
}
