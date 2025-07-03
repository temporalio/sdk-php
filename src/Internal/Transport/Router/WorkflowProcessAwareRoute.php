<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Workflow\Process\Process;

abstract class WorkflowProcessAwareRoute extends Route
{
    private const ERROR_PROCESS_NOT_FOUND = 'Workflow with the specified run identifier "%s" not found';

    public function __construct(
        /**
         * @var RepositoryInterface<Process>
         */
        protected RepositoryInterface $running,
    ) {}

    /**
     * @param non-empty-string $runId
     */
    protected function findInstanceOrFail(string $runId): WorkflowInstanceInterface
    {
        return $this->findProcessOrFail($runId)->getWorkflowInstance();
    }

    /**
     * @param non-empty-string $runId
     */
    protected function findProcessOrFail(string $runId): Process
    {
        return $this->running->find($runId) ?? throw new \LogicException(
            \sprintf(self::ERROR_PROCESS_NOT_FOUND, $runId),
        );
    }
}
