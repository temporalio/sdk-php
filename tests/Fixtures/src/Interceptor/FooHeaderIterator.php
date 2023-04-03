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
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\HeaderInterface;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow\WorkflowExecution;

final class FooHeaderIterator implements
    WorkflowOutboundRequestInterceptor,
    ActivityInboundInterceptor,
    WorkflowInboundInterceptor,
    WorkflowClientCallsInterceptor
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

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        return $next($input->with(header: $this->increment($input->header, __FUNCTION__)));
    }

    public function signal(\Temporal\Interceptor\WorkflowClient\SignalInput $input, callable $next): void
    {
        $next($input);
    }

    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        return $next(
            $input->with(
                workflowStartInput: $input->workflowStartInput->with(
                    header: $this->increment($input->workflowStartInput->header, __FUNCTION__),
                ),
            ),
        );
    }

    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    public function query(\Temporal\Interceptor\WorkflowClient\QueryInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    public function cancel(CancelInput $input, callable $next): void
    {
        $next($input);
    }

    public function terminate(TerminateInput $input, callable $next): void
    {
        $next($input);
    }
}
