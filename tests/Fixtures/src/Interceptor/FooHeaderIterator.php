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
use Temporal\DataConverter\HeaderInterface;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundInterceptor;
use Temporal\Worker\Transport\Command\RequestInterface;

final class FooHeaderIterator implements
    WorkflowOutboundInterceptor,
    ActivityInboundInterceptor,
    WorkflowInboundInterceptor
{
    private function increment(HeaderInterface $header, string $key): HeaderInterface
    {
        $value = $header->getValue($key);

        $value = $value === null ? 1 : (int)$value + 1;
        return $header->withValue($key, (string)$value);
    }

    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        return $next($request->withHeader($this->increment($request->getHeader(), __FUNCTION__)));
    }

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        return $next($input->with(header: $this->increment($input->header, __FUNCTION__)));
    }

    public function execute(WorkflowInput $input, callable $next): void
    {
        $next($input->with(header: $this->increment($input->header, __FUNCTION__)));
    }

    public function handleSignal(SignalInput $input, callable $next): void
    {
        $next($input->with(header: $this->increment($input->header, __FUNCTION__)));
    }

    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        return $next($input);
    }
}
