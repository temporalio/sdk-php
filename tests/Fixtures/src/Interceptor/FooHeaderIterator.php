<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Interceptor;

use React\Promise\PromiseInterface;
use Temporal\Activity;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundInterceptor;
use Temporal\Worker\Transport\Command\RequestInterface;

final class FooHeaderIterator implements WorkflowOutboundInterceptor, ActivityInboundInterceptor
{
    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        $header = $request->getHeader();
        $foo = $header->getValue('Foo');

        $foo = $foo === null ? 1 : (int)$foo + 1;
        // Todo: replace with some think like $request->withHeader($header);
        $request->header = $header->withValue('Foo', (string)$foo);

        return $next($request);
    }

    public function handleActivityInbound(callable $next): mixed
    {
        /** @var \Temporal\Internal\Activity\ActivityContext $context */
        $context = Activity::getCurrentContext();

        $header = $context->getHeader();
        $foo = $header->getValue('Foo');
        $foo = $foo === null ? 1 : (int)$foo + 1;
        // Todo: replace with some think like $context->withHeader($header);
        $context->header = $header->withValue('Foo', (string)$foo);

        return $next();
    }
}
