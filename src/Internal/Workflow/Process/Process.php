<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow\Process;

use JetBrains\PhpStorm\Pure;
use Temporal\Client\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Internal\Transport\Request\ContinueAsNew;
use Temporal\Client\Internal\Workflow\Input;
use Temporal\Client\Workflow\ProcessInterface;
use Temporal\Client\Workflow\WorkflowContext;
use Temporal\Client\Workflow\WorkflowContextInterface;

class Process extends Scope implements ProcessInterface
{
    /**
     * @var WorkflowInstanceInterface
     */
    private WorkflowInstanceInterface $instance;

    /**
     * @param Input $input
     * @param ServiceContainer $services
     * @param WorkflowInstanceInterface $instance
     */
    public function __construct(Input $input, ServiceContainer $services, WorkflowInstanceInterface $instance)
    {
        $this->instance = $instance;
        $context = new WorkflowContext($this, $services, $input);

        parent::__construct($context, $services, $instance->getHandler(), $context->getArguments());

        $services->running->add($this);
        $this->next();
    }

    /**
     * @return WorkflowInstanceInterface
     */
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->instance;
    }

    /**
     * @return WorkflowContextInterface
     */
    public function getContext(): WorkflowContextInterface
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        $info = $this->context->getInfo();

        return $info->execution->runId;
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
     * @return void
     */
    public function cancel(): void
    {
        $this->services->running->pull($this->getId());

        parent::cancel();
    }
}
