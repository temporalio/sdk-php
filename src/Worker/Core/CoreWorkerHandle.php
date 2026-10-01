<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

/**
 * @internal
 */
final class CoreWorkerHandle
{
    public function __construct(
        public readonly \FFI\CData $core,
        public readonly string $taskQueue,
        public readonly WorkflowActivations $activations,
        public readonly bool $workflows,
        public readonly int $activityPolls,
    ) {}
}
