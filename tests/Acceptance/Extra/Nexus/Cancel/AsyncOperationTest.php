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

        // Per Nexus spec (and the Go reference handler in nexus-rpc/sdk-go), an
        // async StartOperation returns `201 Created` with a JSON OperationInfo
        // body carrying the token. Pin both the status and the token shape so
        // the test name actually matches what is asserted.
        self::assertSame(201, $code, "Expected 201 Created for async start, got {$code}. Body: {$resp}");

        $decoded = \json_decode($resp, true);
        self::assertIsArray($decoded, "Async start body must be JSON OperationInfo. Body: {$resp}");
        // Spec field is `token`; tolerate `operationToken` for forward-compat
        // with any future server rename.
        $token = $decoded['token'] ?? $decoded['operationToken'] ?? null;
        self::assertIsString($token, "Async start body must carry a token field. Body: {$resp}");
        self::assertNotSame('', $token, 'Operation token must be non-empty.');
    }

    /**
     * Async start without `Nexus-Callback-Url`. Whether Temporal accepts
     * callback-less async starts is policy on the server side: it can return
     * either `201 Created` (accepted) or a 4xx (refused for missing required
     * header). What we lock down here is:
     *   - the SDK handler itself never produces a 5xx (no internal crash),
     *   - on success (any 2xx) the body is a real OperationInfo with a
     *     non-empty token — i.e. the handler genuinely produced a token,
     *     not just an opaque 200.
     */
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

        [$code, $resp] = $helper->postOperation(
            $endpointId,
            'AsyncJobService',
            'startJob',
            'payload-no-cb',
        );

        self::assertLessThan(500, $code, "Handler must not crash with 5xx. Body: {$resp}");

        if ($code >= 200 && $code < 300) {
            $decoded = \json_decode($resp, true);
            self::assertIsArray($decoded, "2xx async start must carry JSON OperationInfo. Body: {$resp}");
            $token = $decoded['token'] ?? $decoded['operationToken'] ?? null;
            self::assertIsString($token, "2xx async start must carry a token field. Body: {$resp}");
            self::assertNotSame('', $token, 'Operation token must be non-empty on a 2xx response.');
        }
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
