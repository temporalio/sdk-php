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
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * Don't implement the interface directly, extend {@see \Temporal\Interceptor\WorkflowOutboundRequestInterceptor}
 * instead.
 * The interface might be extended in the future.
 *
 * @internal
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
