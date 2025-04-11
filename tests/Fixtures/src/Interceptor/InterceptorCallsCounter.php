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
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowOutboundRequestInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
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
    use WorkflowOutboundRequestInterceptorTrait;
    use ActivityInboundInterceptorTrait;
    use WorkflowInboundCallsInterceptorTrait;
    use WorkflowClientCallsInterceptorTrait;

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

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        return $next($input->with(header: $this->increment($input->header, __FUNCTION__)));
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

    public function updateWithStart(UpdateWithStartInput $input, callable $next): array
    {
        return $next(
            $input->with(
                workflowStartInput: $input->workflowStartInput->with(
                    header: $this->increment($input->workflowStartInput->header, __FUNCTION__),
                ),
            ),
        );
    }
}
