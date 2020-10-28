<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use React\Promise\ExtendedPromiseInterface;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\WorkflowDeclarationInterface;

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

        $result = $handler($this->context, $args);

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
        Workflow::setCurrentProcess($this);

        if ($this->generator === null) {
            throw new \LogicException('Workflow process is not running');
        }

        if (!$this->generator->valid()) {
            $this->context->complete($this->generator->getReturn());

            return;
        }

        /** @var ExtendedPromiseInterface $promise */
        $promise = $this->generator->current();

        $promise
            ->otherwise(function (\Throwable $e) {
                $this->generator->throw($e);
            })
            ->then(function (array $result) {
                $this->generator->send($result[0]);
                $this->next();

                return $result[0];
            });
    }
}
