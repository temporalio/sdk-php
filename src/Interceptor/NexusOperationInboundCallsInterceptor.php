<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
use Temporal\Interceptor\Trait\NexusOperationInboundCallsInterceptorTrait;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Nexus\Handler\OperationStartResult;

/**
 * It's recommended to use `NexusOperationInboundCallsInterceptorTrait` when implementing this interface
 * because the interface might be extended in the future. The trait will provide forward compatibility.
 *
 * ```php
 * class MyNexusOperationInboundCallsInterceptor implements NexusOperationInboundCallsInterceptor
 * {
 *     use NexusOperationInboundCallsInterceptorTrait;
 *
 *     public function startOperation(StartOperationInput $input, callable $next): OperationStartResult
 *     {
 *         if ($input->operationContext->headers->get('authorization') !== 'expected-token') {
 *             throw HandlerException::create(ErrorType::Unauthorized, 'Unauthorized');
 *         }
 *
 *         return $next($input);
 *     }
 * }
 * ```
 *
 * @see NexusOperationInboundCallsInterceptorTrait
 */
interface NexusOperationInboundCallsInterceptor extends Interceptor
{
    /**
     * @param callable(StartOperationInput): OperationStartResult $next
     */
    public function startOperation(StartOperationInput $input, callable $next): OperationStartResult;

    /**
     * @param callable(CancelOperationInput): void $next
     */
    public function cancelOperation(CancelOperationInput $input, callable $next): void;
}
