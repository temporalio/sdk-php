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
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow;

final class Process
{
    /**
     * @var WorkflowContext
     */
    private WorkflowContext $env;

    /**
     * @var \Generator|null
     */
    private ?\Generator $generator = null;

    /**
     * @var WorkflowDeclarationInterface
     */
    private WorkflowDeclarationInterface $declaration;

    /**
     * @var WorkerInterface
     */
    private WorkerInterface $worker;

    /**
     * @param WorkerInterface $worker
     * @param WorkflowContext $ctx
     * @param WorkflowDeclarationInterface $decl
     */
    public function __construct(WorkerInterface $worker, WorkflowContext $ctx, WorkflowDeclarationInterface $decl)
    {
        $this->env = $ctx;
        $this->worker = $worker;
        $this->declaration = clone $decl;
    }

    /**
     * @return WorkflowDeclarationInterface
     */
    public function getDeclaration(): WorkflowDeclarationInterface
    {
        return $this->declaration;
    }

    /**
     * @param array $args
     */
    public function start(array $args): void
    {
        if ($this->generator !== null) {
            throw new \LogicException('Workflow already has been started');
        }

        $handler = $this->declaration->getHandler();

        $result = $handler($this->env, ...$args);

        if ($result instanceof \Generator) {
            $this->generator = $result;
        } else {
            $this->env->complete($result);
        }
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
            $this->env->complete($this->generator->getReturn());

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
        return $this->env;
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
