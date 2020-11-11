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
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Declaration\WorkflowInstance;
use Temporal\Client\Workflow\RunningWorkflows;

final class InvokeQuery extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_QUERY_NOT_FOUND = 'unknown queryType %s. KnownQueryTypes=[%s]';

    /**
     * @var Collection<WorkflowPrototype>
     */
    private Collection $workflows;

    /**
     * @param Collection<WorkflowPrototype> $workflows
     * @param RunningWorkflows $running
     */
    public function __construct(Collection $workflows, RunningWorkflows $running)
    {
        $this->workflows = $workflows;

        parent::__construct($running);
    }

    /**
     * @return iterable|string[]
     */
    private function getAvailableQueryNames(): iterable
    {
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
        ['runId' => $runId, 'name' => $name] = $payload;

        $instance = $this->findInstanceOrFail($runId);
        $handler = $this->findQueryHandlerOrFail($instance, $name);

        $resolver->resolve($handler($payload['args'] ?? []));
    }

    /**
     * @param WorkflowInstance $instance
     * @param string $name
     * @return \Closure|null
     */
    private function findQueryHandlerOrFail(WorkflowInstance $instance, string $name): ?\Closure
    {
        $handler = $instance->findQueryHandler($name);

        if ($handler === null) {
            $available = \implode(' ', [...$this->getAvailableQueryNames()]);

            throw new \LogicException(\sprintf(self::ERROR_QUERY_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
