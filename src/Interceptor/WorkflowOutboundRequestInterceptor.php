<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use React\Promise\PromiseInterface;
use Temporal\Interceptor\Trait\WorkflowOutboundRequestInterceptorTrait;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * Intercept a request before it's sent to RoadRunner.
 *
 * It's recommended to use {@see WorkflowOutboundRequestInterceptorTrait} when implementing this interface because
 * the interface might be extended in the future. The trait will provide forward compatibility.
 *
 * @psalm-immutable
 */
interface WorkflowOutboundRequestInterceptor extends Interceptor
{
    /**
     * @param RequestInterface $request
     * @param callable(RequestInterface): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface;
}
