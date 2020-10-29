<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Router;

use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;

class DestroyWorkflow extends Route
{
    /**
     * @var string
     */
    private const ERROR_RID_NOT_DEFINED =
        'Killing a workflow requires the id (rid argument) ' .
        'of the running workflow process';

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

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
        $this->running = $running;
        $this->worker = $worker;
    }

    /**
     * @param array $payload
     * @param array $headers
     * @return array
     */
    public function handle(array $payload, array $headers): array
    {
        $workflowRunId = $payload['rid'] ?? $headers['rid'] ?? null;

        if ($workflowRunId === null) {
            throw new \InvalidArgumentException(self::ERROR_RID_NOT_DEFINED);
        }

        $requests = $this->running->kill($workflowRunId, $this->worker->getClient());

        return ['rid' => $workflowRunId, 'cancelRequests' => $requests];
    }
}
