<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow;

final class RunningWorkflows
{
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to kill workflow because workflow process #%s was not found';

    /**
     * @var array
     */
    private array $processes = [];

    /**
     * @var WorkflowInstantiator
     */
    private WorkflowInstantiator $instantiator;

    /**
     * RunningWorkflows constructor.
     */
    public function __construct()
    {
        $this->instantiator = new WorkflowInstantiator();
    }

    /**
     * @param WorkerInterface $worker
     * @param WorkflowContext $ctx
     * @param WorkflowPrototype $prototype
     * @return Process
     */
    public function run(WorkerInterface $worker, WorkflowContext $ctx, WorkflowPrototype $prototype): Process
    {
        $info = $ctx->getInfo();

        $instance = $this->instantiator->instantiate($prototype);

        return $this->processes[$info->execution->runId] = new Process($worker, $ctx, $instance);
    }

    /**
     * @param string $runId
     * @return Process|null
     */
    public function find(string $runId): ?Process
    {
        return $this->processes[$runId] ?? null;
    }

    /**
     * @param string $runId
     * @param ClientInterface $client
     * @return array
     */
    public function kill(string $runId, ClientInterface $client): array
    {
        $process = $this->find($runId);

        if ($process === null) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId));
        }

        Workflow::setCurrentContext(null);
        unset($this->processes[$runId]);
        $context = $process->getContext();

        $requests = $context->getRequestIdentifiers();

        foreach ($requests as $id) {
            $client->cancel($id);
        }

        return $requests;
    }
}
