<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\Info\WorkflowExecution;
use Temporal\Client\Workflow\Process;
use Temporal\Client\Workflow\RunningWorkflows;

class DestroyWorkflow extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_RID_NOT_DEFINED =
        'Killing a workflow requires the id (rid argument) ' .
        'of the running workflow process';

    /**
     * @var Worker
     */
    private Worker $worker;

    /**
     * @param RunningWorkflows $running
     * @param Worker $worker
     */
    public function __construct(RunningWorkflows $running, Worker $worker)
    {
        $this->worker = $worker;

        parent::__construct($running);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        ['runId' => $runId] = $payload;

        $process = $this->findProcessOrFail($runId);

        $requests = $this->running->kill($runId, $this->worker->getClient());

        $info = $process->getContext()->getInfo();

        $resolver->resolve([
            'WorkflowExecution' => $info->execution,
            'CancelRequests'    => $requests,
        ]);
    }
}
