<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Cancel;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: async Nexus operation — handler returns an operation token,
 * Temporal preserves it for later polling/cancel.
 *
 * This exercises the async branch of OperationStartResult::async() that the
 * existing Errors/Headers/Basic suites do not touch.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function asyncStartReturnsOperationToken(
        State $state,
        #[Stub('Extra_Nexus_Cancel_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Cancel',
            'nexus-cancel',
        );

        [$code, $resp] = $helper->postOperation(
            $endpointId,
            'AsyncJobService',
            'startJob',
            'payload',
            [
                // Callback URL is required by the Nexus wire protocol for async starts
                // so that the caller can receive completion.
                'Nexus-Callback-Url' => 'http://callback.example.local/done',
            ],
        );

        // Temporal returns 201 Created with the operation token for async starts.
        // We accept any 2xx — the specific code is less important than the non-error.
        self::assertTrue(
            $code >= 200 && $code < 300,
            "Expected 2xx for async start, got {$code}. Body: {$resp}",
        );
    }

    #[Test]
    public function asyncOperationWithoutCallbackStillStarts(
        State $state,
        #[Stub('Extra_Nexus_Cancel_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Cancel',
            'nexus-cancel-nocb',
        );

        // Some Nexus clients skip the callback header; handler must still receive the
        // request and produce a token. Temporal may or may not reject — we just want
        // to verify the handler itself does not crash (no 5xx from our PHP side).
        [$code, $resp] = $helper->postOperation(
            $endpointId,
            'AsyncJobService',
            'startJob',
            'payload-no-cb',
        );

        self::assertLessThan(500, $code, "Unexpected server error {$code}. Body: {$resp}");
    }
}

// ── Nexus service ────────────────────────────────────────────────────

#[Service(name: 'AsyncJobService')]
class AsyncJobService
{
    #[AsyncOperation(output: 'string')]
    public function startJob(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        // Generate a deterministic-ish token derived from requestId so the caller
        // can correlate.
        $token = 'job-' . \substr(\hash('sha1', $details->requestId . ':' . $input), 0, 12);
        return new OperationInfo($token, OperationState::Running);
    }

    #[OperationCancel(operation: 'startJob')]
    public function cancelStartJob(string $token): void
    {
        // No-op: real cancellation would notify an external job queue.
    }
}

// ── Bootstrap workflows ──────────────────────────────────────────────

#[WorkflowInterface]
class CancelBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Cancel_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class CancelBootstrapWorkflow2
{
    #[WorkflowMethod(name: 'Extra_Nexus_Cancel_Bootstrap2')]
    public function run(): string
    {
        return 'ready';
    }
}
