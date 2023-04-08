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
use RuntimeException;
use Temporal\DataConverter\EncodedHeader;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\QueryInput as ClientQueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput as ClientSignalInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundInterceptor;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Tests\Workflow\Header\EmptyHeaderWorkflow;
use Temporal\Tests\Workflow\Header\ChildedHeaderWorkflow;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;

/**
 * Interceptor thar helps to test headers.
 * @see \Temporal\Tests\Functional\Interceptor\HeaderTestCase
 */
final class HeaderChanger implements
    WorkflowOutboundRequestInterceptor,
    WorkflowInboundInterceptor,
    WorkflowClientCallsInterceptor
{
    private function processInput(StartInput $input): StartInput
    {
        if ($input->workflowType === EmptyHeaderWorkflow::WORKFLOW_NAME) {
            return $input->with(header: EncodedHeader::empty());
        }

        return $input;
    }

    private function processRequest(RequestInterface $request): object
    {
        if (Workflow::getInfo()->type->name === EmptyHeaderWorkflow::WORKFLOW_NAME) {
            return $request->withHeader(EncodedHeader::empty());
        }

        return $request;
    }

    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        return match ($request::class) {
            ExecuteActivity::class => $this->executeActivity($request, $next),
            default => $next($this->processRequest($request)),
        };
    }

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        return $next($this->processInput($input));
    }

    public function signal(ClientSignalInput $input, callable $next): void
    {
        $next($input);
    }

    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    public function query(ClientQueryInput $input, callable $next): ?EncodedValues
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

    public function execute(WorkflowInput $input, callable $next): void
    {
        if ($input->info->type->name === EmptyHeaderWorkflow::WORKFLOW_NAME) {
            match (false) {
                /** @see self::start() must clear the Header after {@see InterceptorCallsCounter::start()} */
                $input->header->getValue('start') === null => throw new RuntimeException('Client Header must be empty'),
                default => $next($input->with(header: EncodedHeader::empty())),
            };
            return;
        }

        if ($input->info->type->name === ChildedHeaderWorkflow::WORKFLOW_NAME) {
            $values = $input->arguments->getValue(0, null);
            $header = $input->header;
            if ($values !== null) {
                $header = EncodedHeader::fromValues((array) $values);
            }
            $next($input->with(header: $header));

            return;
        }

        $next($input);
    }

    public function handleSignal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        return $next($input);
    }

    /**
     * @param ExecuteActivity $request
     * @param callable(ExecuteActivity): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function executeActivity(ExecuteActivity $request, callable $next): PromiseInterface
    {
        if (Workflow::getInfo()->type->name === ChildedHeaderWorkflow::WORKFLOW_NAME) {
            $header = Workflow::getInput()->count() >= 3 ? Workflow::getInput()->getValue(2, null) : null;
            if ($header !== null) {
                $request = $request->withHeader(EncodedHeader::fromValues((array)$header));
            }
        }

        return $next($request);
    }
}
