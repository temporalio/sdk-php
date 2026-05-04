<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\NexusOperationInbound\NexusOperationCancelInput;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationStartInput;
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
 *     public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult
 *     {
 *         if (($input->context->headers['authorization'] ?? null) !== 'expected-token') {
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
     * @param callable(NexusOperationStartInput): OperationStartResult $next
     */
    public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult;

    /**
     * @param callable(NexusOperationCancelInput): void $next
     */
    public function cancelNexusOperation(NexusOperationCancelInput $input, callable $next): void;
}
