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
use Temporal\Activity\ActivityContextInterface;
use Temporal\DataConverter\HeaderInterface;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundInterceptor;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\WorkflowContextInterface;

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
        // Todo: replace with some think like $request->withHeader($header);
        $request->header = $this->increment($request->getHeader(), __FUNCTION__);

        return $next($request);
    }

    public function handleActivityInbound(ActivityContextInterface $context, callable $next): mixed
    {
        // Todo: replace with some think like $context->withHeader($header);
        $context->header = $this->increment($context->getHeader(), __FUNCTION__);

        return $next($context);
    }

    public function execute(WorkflowContextInterface $context, callable $next): void
    {
        // Todo: replace with some think like $context->withHeader($header);
        $context->input->header = $this->increment($context->getHeader(), __FUNCTION__);

        $next($context);
    }

    public function handleSignal(WorkflowContextInterface $context, callable $next): void
    {
        // Todo: replace with some think like $context->withHeader($header);
        $context->input->header = $this->increment($context->getHeader(), __FUNCTION__);

        $next($context);
    }

    public function handleQuery(WorkflowContextInterface $context, callable $next): void
    {
        // Todo: replace with some think like $context->withHeader($header);
        $context->input->header = $this->increment($context->getHeader(), __FUNCTION__);

        $next($context);
    }
}
