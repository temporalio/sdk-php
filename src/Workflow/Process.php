<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use React\Promise\PromisorInterface;
use Temporal\Client\Internal\Declaration\WorkflowInstance;
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow;

final class Process
{
    /**
     * @var WorkflowContext
     */
    private WorkflowContext $context;

    /**
     * @var \Generator
     */
    private \Generator $generator;

    /**
     * @var WorkflowInstance
     */
    private WorkflowInstance $instance;

    /**
     * @var WorkerInterface
     */
    private WorkerInterface $worker;

    /**
     * @param WorkerInterface $worker
     * @param WorkflowContext $ctx
     * @param WorkflowInstance $instance
     */
    public function __construct(WorkerInterface $worker, WorkflowContext $ctx, WorkflowInstance $instance)
    {
        $this->worker = $worker;
        $this->context = $ctx;
        $this->instance = $instance;

        $this->generator = $this->start();
    }

    /**
     * @return \Generator
     */
    private function start(): \Generator
    {
        $handler = $this->instance->getHandler();
        $result = $handler($this->getArguments());

        if ($result instanceof \Generator) {
            yield from $result;

            return $result->getReturn();
        }

        return $result;
    }

    /**
     * @return array
     */
    private function getArguments(): array
    {
        $arguments = [
            WorkflowContextInterface::class => $this->context,
        ];

        return \array_merge($arguments, $this->context->getArguments());
    }

    /**
     * @return WorkflowInstance
     */
    public function getInstance(): WorkflowInstance
    {
        return $this->instance;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        Workflow::setCurrentContext($this->getContext());

        if ($this->generator === null) {
            throw new \LogicException('Workflow process is not running');
        }

        if (! $this->generator->valid()) {
            $this->context->complete($this->generator->getReturn());

            return;
        }

        /** @var ExtendedPromiseInterface|\Generator $current */
        $current = $this->generator->current();

        switch (true) {
            case $current instanceof PromiseInterface:
                $this->nextPromise($current);
                break;

            case $current instanceof PromisorInterface:
                // todo: must handle on complete (!)
                $this->nextPromise($current->promise());
                break;

            case $current instanceof \Generator:
                // TODO: inject coroutine process

            default:
                $this->generator->send($current);
        }
    }

    /**
     * @return WorkflowContext
     */
    public function getContext(): WorkflowContext
    {
        return $this->context;
    }

    /**
     * @param PromiseInterface $promise
     */
    private function nextPromise(PromiseInterface $promise): void
    {
        $onFulfilled = function ($result) {
            $this->worker->once(WorkerInterface::ON_TICK, function () use ($result) {
                Workflow::setCurrentContext($this->getContext());

                $this->generator->send($result);
                $this->next();
            });

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            $this->worker->once(WorkerInterface::ON_TICK, function () use ($e) {
                Workflow::setCurrentContext($this->getContext());

                $this->generator->throw($e);
            });

            throw $e;
        };

        $promise->then($onFulfilled, $onRejected);
    }
}
