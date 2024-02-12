<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Internal\Interceptor\Interceptor;

/**
 * It's recommended to use {@see WorkflowInboundCallsInterceptorTrait} when implementing this interface because
 * the interface might be extended in the future. The trait will provide forward compatibility.
 *
 * @psalm-immutable
 */
interface WorkflowInboundCallsInterceptor extends Interceptor
{
    /**
     * @param WorkflowInput $input
     * @param callable(WorkflowInput): void $next
     */
    public function execute(WorkflowInput $input, callable $next): void;

    /**
     * @param SignalInput $input
     * @param callable(SignalInput): void $next
     */
    public function handleSignal(SignalInput $input, callable $next): void;

    /**
     * @param QueryInput $input
     * @param callable(QueryInput): mixed $next
     */
    public function handleQuery(QueryInput $input, callable $next): mixed;

    /**
     * @param UpdateInput $input
     * @param callable(UpdateInput): mixed $next
     */
    public function handleUpdate(UpdateInput $input, callable $next): mixed;

    /**
     * @param UpdateInput $input
     * @param callable(UpdateInput): void $next
     */
    public function validateUpdate(UpdateInput $input, callable $next): void;
}
