<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\Input;
use Temporal\Workflow\ProcessInterface;
use Temporal\Workflow\WorkflowContext;
use Temporal\Workflow\WorkflowContextInterface;

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
