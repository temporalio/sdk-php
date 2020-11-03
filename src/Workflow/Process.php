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
use Temporal\Client\Future\FutureInterface;
use Temporal\Client\Worker\Loop;
use Temporal\Client\Workflow;

final class Process
{
    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @var \Generator|null
     */
    private ?\Generator $generator = null;

    /**
     * @var WorkflowDeclarationInterface
     */
    private WorkflowDeclarationInterface $declaration;

    /**
     * @param WorkflowContextInterface     $context
     * @param WorkflowDeclarationInterface $declaration
     */
    public function __construct(WorkflowContextInterface $context, WorkflowDeclarationInterface $declaration)
    {
        $this->context = $context;
        $this->declaration = clone $declaration;
    }

    /**
     * @return WorkflowContextInterface
     */
    public function getContext(): WorkflowContextInterface
    {
        return $this->context;
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

        $result = $handler($this->context, ...$args);

        if ($result instanceof \Generator) {
            $this->generator = $result;
        } else {
            $this->context->complete($result);
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

        if (!$this->generator->valid()) {
            $this->context->complete($this->generator->getReturn());

            return;
        }

        /** @var ExtendedPromiseInterface|\Generator $current */
        $current = $this->generator->current();

        switch (true) {
            case $current instanceof PromiseInterface:
                $this->nextPromise($current);
                break;

            case $current instanceof FutureInterface:
                $this->nextPromise($current->promise());
                break;

            case $current instanceof \Generator:
                // TODO: inject coroutine process

            default:
                $this->generator->send($current);
        }
    }

    /**
     * @param PromiseInterface $promise
     */
    private function nextPromise(PromiseInterface $promise): void
    {
        $onFulfilled = function ($result) {
            Loop::onTick(function () use ($result) {
                Workflow::setCurrentContext($this->getContext());
                $this->generator->send($result);
                $this->next();
            }, Loop::ON_TICK);

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            Loop::onTick(function () use ($e) {
                Workflow::setCurrentContext($this->getContext());
                $this->generator->throw($e);
            }, Loop::ON_TICK);

            throw $e;
        };

        $promise->then($onFulfilled, $onRejected);
    }
}
