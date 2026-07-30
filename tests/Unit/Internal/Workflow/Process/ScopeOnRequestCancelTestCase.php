<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * A non-cancellable request (e.g. the CompleteWorkflow command sent by Process::complete())
 * is still allowed through ScopeContext::request() after the scope was cancelled. Its onRequest
 * handler must NOT fire on registration: firing would call client->cancel() on the just-queued
 * command and silently strip the completion, leaving the workflow unable to finish.
 */
final class ScopeOnRequestCancelTestCase extends TestCase
{
    #[Test]
    public function nonCancellableRequestInCancelledScopeKeepsQueuedCommand(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('isQueued')->willReturn(true);
        $client->expects($this->never())->method('cancel');
        $client->expects($this->never())->method('request');

        $context = $this->createMock(WorkflowContext::class);
        $context->method('getClient')->willReturn($client);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getID')->willReturn(42);

        $scope = new OnRequestProbeScope($context, $this->createMock(ScopeContext::class));
        $scope->cancel();

        $scope->callOnRequest($request, $this->createMock(PromiseInterface::class), cancellable: false);
    }
}

final class OnRequestProbeScope extends Scope
{
    public function __construct(WorkflowContext $context, ScopeContext $scopeContext)
    {
        $this->context = $context;
        $this->scopeContext = $scopeContext;
    }

    public function callOnRequest(RequestInterface $request, PromiseInterface $promise, bool $cancellable = true): void
    {
        $this->onRequest($request, $promise, $cancellable);
    }

    protected function makeCurrent(): void
    {
        // no-op: avoid the global Workflow context facade in isolation
    }
}
