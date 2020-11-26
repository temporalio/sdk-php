<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Transport\DispatcherInterface;
use Temporal\Client\Internal\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Internal\Transport\Router;
use Temporal\Client\Internal\Transport\RouterInterface;
use Temporal\Client\Worker\Worker;

final class WorkflowWorker implements DispatcherInterface
{
    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var string|null
     */
    private ?string $runId = null;

    /**
     * @var bool
     */
    private bool $isReplaying = false;

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $workflows;

    /**
     * @param Collection<WorkflowPrototype> $workflows
     * @param Worker $worker
     */
    public function __construct(Collection $workflows, Worker $worker)
    {
        $this->workflows = new RunningWorkflows();

        $this->router = new Router();
        $this->router->add(new Router\StartWorkflow($workflows, $this->workflows, $worker));
        $this->router->add(new Router\InvokeQuery($this->workflows));
        $this->router->add(new Router\InvokeSignal($this->workflows, $worker));
        $this->router->add(new Router\DestroyWorkflow($this->workflows, $worker));
        $this->router->add(new Router\StackTrace($this->workflows));
    }

    /**
     * @return RunningWorkflows
     */
    public function getRunningWorkflows(): RunningWorkflows
    {
        return $this->workflows;
    }

    /**
     * @return string|null
     */
    public function getCurrentRunId(): ?string
    {
        return $this->runId;
    }

    /**
     * @return bool
     */
    public function isReplaying(): bool
    {
        return $this->isReplaying;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(RequestInterface $request, array $headers = []): PromiseInterface
    {
        if (isset($headers['rid'])) {
            $this->runId = $headers['rid'];
        }

        $this->isReplaying = isset($headers['replay']) && $headers['replay'] === true;

        return $this->router->dispatch($request, $headers);
    }
}
