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
use Temporal\Interceptor\Header;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Tests\Workflow\Header\ChildedHeaderWorkflow;
use Temporal\Tests\Workflow\Header\EmptyHeaderWorkflow;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;

/**
 * Interceptor thar helps to test headers.
 *
 * @see \Temporal\Tests\Functional\Interceptor\HeaderTestCase
 * @psalm-immutable
 */
final class HeaderChanger implements
    WorkflowOutboundRequestInterceptor,
    WorkflowInboundCallsInterceptor,
    WorkflowClientCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;
    use WorkflowClientCallsInterceptorTrait;

    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        return match ($request::class) {
            ExecuteActivity::class => $this->executeActivity($request, $next),
            default => $next($this->processRequest($request)),
        };
    }

    public function execute(WorkflowInput $input, callable $next): void
    {
        if ($input->info->type->name === EmptyHeaderWorkflow::WORKFLOW_NAME) {
            match (false) {
                /** @see self::start() must clear the Header after {@see InterceptorCallsCounter::start()} */
                $input->header->getValue('start') === null => throw new \RuntimeException('Client Header must be empty'),
                default => $next($input->with(header: Header::empty())),
            };
            return;
        }

        if ($input->info->type->name === ChildedHeaderWorkflow::WORKFLOW_NAME) {
            $values = $input->arguments->getValue(0, null);
            $header = $input->header;
            if ($values !== null) {
                $header = Header::fromValues((array) $values);
            }

            $next($input->with(header: $header));

            return;
        }

        $next($input);
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
                $request = $request->withHeader(Header::fromValues((array) $header));
            }
        }

        return $next($request);
    }

    private function processRequest(RequestInterface $request): object
    {
        if (Workflow::getInfo()->type->name === EmptyHeaderWorkflow::WORKFLOW_NAME) {
            return $request->withHeader(Header::empty());
        }

        return $request;
    }
}
