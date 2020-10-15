<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

/**
 * @internal RunningWorkflows is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Workflow
 */
final class RunningWorkflows
{
    /**
     * @var array
     */
    private array $processes = [];

    /**
     * @param WorkflowContextInterface $context
     * @return Process
     */
    public function run(WorkflowContextInterface $context): Process
    {
        return $this->processes[$context->getRunId()] = new Process($context);
    }

    /**
     * @param WorkflowContextInterface $context
     * @return Process|null
     */
    public function find(WorkflowContextInterface $context): ?Process
    {
        return $this->processes[$context->getRunId()] ?? null;
    }
}
