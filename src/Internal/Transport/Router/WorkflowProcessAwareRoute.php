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
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_FOUND = 'Workflow with the specified run identifier "%s" not found';

    /**
     * @param RepositoryInterface $running
     */
    public function __construct(
        protected RepositoryInterface $running
    ) {
    }

    /**
     * @param string $runId
     * @return WorkflowInstanceInterface
     */
    protected function findInstanceOrFail(string $runId): WorkflowInstanceInterface
    {
        $process = $this->findProcessOrFail($runId);

        return $process->getWorkflowInstance();
    }

    /**
     * @param string $runId
     * @return Process
     */
    protected function findProcessOrFail(string $runId): Process
    {
        $process = $this->running->find($runId);

        if ($process === null) {
            throw new \LogicException(\sprintf(self::ERROR_PROCESS_NOT_FOUND, $runId));
        }

        return $process;
    }
}
