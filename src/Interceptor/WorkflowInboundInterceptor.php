<?php

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Workflow\WorkflowContextInterface;

interface WorkflowInboundInterceptor extends Interceptor
{
    /**
     * @param WorkflowContextInterface $context
     * @param callable(WorkflowContextInterface): void $next
     */
    public function execute(WorkflowContextInterface $context, callable $next): void;

    /**
     * @param WorkflowContextInterface $context
     * @param callable(WorkflowContextInterface): void $next
     */
    public function handleSignal(WorkflowContextInterface $context, string $signal, callable $next): void;

    /**
     * @param WorkflowContextInterface $context
     * @param callable(WorkflowContextInterface): void $next
     * todo: add some context about query name
     */
    public function handleQuery(WorkflowContextInterface $context, callable $next): void;
}
