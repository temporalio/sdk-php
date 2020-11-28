<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use Temporal\Client\Internal\Declaration\WorkflowInstance;
use Temporal\Client\Internal\Workflow\Process;
use Temporal\Client\Internal\Workflow\RunningWorkflows;

abstract class WorkflowProcessAwareRoute extends Route
{
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_FOUND = 'Workflow with the specified run identifier "%s" not found';

    /**
     * @var RunningWorkflows
     */
    protected RunningWorkflows $running;

    /**
     * @param RunningWorkflows $running
     */
    public function __construct(RunningWorkflows $running)
    {
        $this->running = $running;
    }

    /**
     * @param string $runId
     * @return WorkflowInstance
     */
    protected function findInstanceOrFail(string $runId): WorkflowInstance
    {
        $process = $this->findProcessOrFail($runId);

        return $process->getInstance();
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
