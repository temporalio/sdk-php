<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

final class WorkflowSerializationContext implements HasWorkflowSerializationContext
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
    ) {}

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }
}
