<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Activity\ActivityInfo;

final class ActivitySerializationContext implements HasWorkflowSerializationContext
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $activityType,
        public readonly string $taskQueue,
        public readonly ?string $workflowId = null,
        public readonly ?string $workflowType = null,
        public readonly bool $isLocal = false,
    ) {}

    public static function fromActivityInfo(ActivityInfo $info, bool $isLocal): self
    {
        return new self(
            namespace: $info->workflowNamespace,
            activityType: $info->type->name,
            taskQueue: $info->taskQueue,
            workflowId: $info->workflowExecution?->getID(),
            workflowType: $info->workflowType?->name,
            isLocal: $isLocal,
        );
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }
}
