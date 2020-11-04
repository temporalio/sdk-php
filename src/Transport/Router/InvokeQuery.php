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
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Workflow\RunningWorkflows;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;

final class InvokeQuery extends Route
{
    /**
     * @var string
     */
    private const ERROR_RID_NOT_DEFINED =
        'Invoking query of a workflow requires the id (rid argument) ' .
        'of the running workflow process';

    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_FOUND = 'Workflow with the specified run id %s not found';

    /**
     * @var string
     */
    private const ERROR_QUERY_NOT_FOUND = 'Workflow query handler "%s" not found, known queries [%s]';

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
     * @param RunningWorkflows $running
     */
    public function __construct(CollectionInterface $workflows, RunningWorkflows $running)
    {
        $this->running = $running;
        $this->workflows = $workflows;
    }

    /**
     * @return iterable|string[]
     */
    private function getAvailableQueryNames(): iterable
    {
        /** @var WorkflowDeclarationInterface $workflow */
        foreach ($this->workflows as $workflow) {
            foreach ($workflow->getQueryHandlers() as $name => $_) {
                yield $name;
            }
        }
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

        $declaration = $workflow->getDeclaration();

        $handler = $declaration->findQueryHandler($payload['name']);

        if ($handler === null) {
            throw new \LogicException(\vsprintf(self::ERROR_QUERY_NOT_FOUND, [
                $payload['name'],
                \implode(', ', [...$this->getAvailableQueryNames()])
            ]));
        }

        $resolver->resolve(
            $handler(...($payload['args'] ?? []))
        );
    }
}
