<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Workflow\WorkflowInfo;

final class WorkflowSerializationContext implements HasWorkflowSerializationContext
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
    ) {}

    public static function fromInfo(WorkflowInfo $info): self
    {
        return new self($info->namespace, $info->execution->getID());
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }
}
