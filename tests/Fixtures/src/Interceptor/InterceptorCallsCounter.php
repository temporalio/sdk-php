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
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;

/**
 * Adds in the Header a key with an interceptor method name that was called
 * with value of the number of times it was called.
 *
 * Note: some methods like {@see self::signal()} have no ability to change the header.
 * @psalm-immutable
 */
final class InterceptorCallsCounter implements
    WorkflowOutboundRequestInterceptor,
    ActivityInboundInterceptor,
    WorkflowInboundCallsInterceptor,
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
        $header = $this->increment(Workflow::getCurrentContext()->getHeader(), $request->getName());
        return $next($request->withHeader($header));
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
