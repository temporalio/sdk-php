<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use Temporal\Client\Workflow\WorkflowDeclarationInterface;

final class RunningWorkflows
{
    /**
     * @var array
     */
    private array $processes = [];

    /**
     * @param WorkflowContextInterface $context
     * @param WorkflowDeclarationInterface $declaration
     * @return Process
     */
    public function run(WorkflowContextInterface $context, WorkflowDeclarationInterface $declaration): Process
    {
        return $this->processes[$context->getRunId()] = new Process($context, $declaration);
    }

    /**
     * @param string $runId
     * @return Process|null
     */
    public function find(string $runId): ?Process
    {
        return $this->processes[$runId] ?? null;
    }
}
