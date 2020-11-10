<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Workflow\RunningWorkflows;

class GetStacktrace extends Route
{
    /**
     * @var string
     */
    private const ERROR_RID_NOT_DEFINED =
        'Invoking a workflow stack trace requires the id (rid argument) of the running workflow process';

    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_FOUND = 'Workflow with the specified run id %s not found';

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @psalm-var CollectionInterface<WorkflowDeclarationInterface>
     *
     * @var CollectionInterface
     */
    private CollectionInterface $workflows;

    /**
     * @psalm-param CollectionInterface<WorkflowDeclarationInterface> $workflows
     *
     * @param CollectionInterface $workflows
     * @param RunningWorkflows    $running
     */
    public function __construct(CollectionInterface $workflows, RunningWorkflows $running)
    {
        $this->running = $running;
        $this->workflows = $workflows;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $workflowRunId = $payload['runId'] ?? null;

        if ($workflowRunId === null) {
            throw new \InvalidArgumentException(self::ERROR_RID_NOT_DEFINED);
        }

        $workflow = $this->running->find($workflowRunId);

        if ($workflow === null) {
            throw new \LogicException(\sprintf(self::ERROR_PROCESS_NOT_FOUND, $workflowRunId));
        }

        $resolver->resolve(
            $this->prepareBackTrace($workflow->getContext()->getDebugBacktrace())
        );
    }

    /**
     * @param array $backtrace
     * @return array
     */
    private function prepareBackTrace(array $backtrace)
    {
        return $backtrace;
    }
}
