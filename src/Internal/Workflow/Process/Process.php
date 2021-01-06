<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use JetBrains\PhpStorm\Pure;
use Temporal\Exception\CancellationException;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ProcessInterface;
use Temporal\Workflow\WorkflowContext;

class Process extends Scope implements ProcessInterface
{
    /**
     * Process constructor.
     * @param ServiceContainer $services
     * @param WorkflowContext $context
     */
    public function __construct(
        ServiceContainer $services,
        WorkflowContext $context
    ) {
        parent::__construct(
            $services,
            $context,
            $context->getWorkflowInstance()->getHandler(),
            $context->getArguments()
        );
    }

    /**
     * @return WorkflowInstanceInterface
     */
    #[Pure]
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->getContext()->getWorkflowInstance();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->context->getInfo()->execution->runId;
    }

    /**
     * @param callable $handler
     * @param bool $detached
     * @return CancellationScopeInterface
     */
    public function createScope(callable $handler, bool $detached): CancellationScopeInterface
    {

    }

    /**
     * @param mixed $result
     */
    protected function onComplete($result): void
    {
        if ($this->context->isContinuedAsNew()) {
            return;
        }

        $this->context->complete($result ?? $this->coroutine->getReturn());
    }

    /**
     * @param \Throwable $e
     */
    protected function onException(\Throwable $e)
    {
        if ($e instanceof CancellationException) {
            $this->cancel();
        }

        // todo: complete with error (!)
        $this->context->complete($e);
    }
}
