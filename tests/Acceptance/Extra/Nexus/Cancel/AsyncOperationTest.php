<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Cancel;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHttpClient;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Async Nexus operation over HTTP: handler returns an operation token (manual-token start). */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function asyncStartReturnsOperationToken(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_Cancel_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-cancel');

        [$code, $resp, ] = $http->post(
            $endpoint,
            'AsyncJobService',
            'startJob',
            'payload',
            ['Nexus-Callback-Url' => 'http://callback.example.local/done'],
        );

        // Nexus spec: async StartOperation → 201 Created with a JSON OperationInfo body.
        self::assertSame(201, $code, "Expected 201 Created for async start, got {$code}. Body: {$resp}");

        $decoded = \json_decode($resp, true);
        self::assertIsArray($decoded, "Async start body must be JSON OperationInfo. Body: {$resp}");
        // Spec field is `token`; tolerate `operationToken` for forward-compat.
        $token = $decoded['token'] ?? $decoded['operationToken'] ?? null;
        self::assertIsString($token, "Async start body must carry a token field. Body: {$resp}");
        self::assertNotSame('', $token, 'Operation token must be non-empty.');
    }

    /** Async start without `Nexus-Callback-Url`: server policy decides 201 vs 4xx; never a 5xx. */
    #[Test]
    public function asyncOperationWithoutCallbackStillStarts(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_Cancel_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-cancel-nocb');

        [$code, $resp, ] = $http->post(
            $endpoint,
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
        } else {
            self::assertStringContainsStringIgnoringCase(
                'callback',
                $resp,
                "A 4xx refusal must be about the missing callback, not some other failure. Code {$code}. Body: {$resp}",
            );
        }
    }
}

// ── Nexus service ────────────────────────────────────────────────────

#[Service(name: 'AsyncJobService')]
class AsyncJobService
{
    #[AsyncOperation(output: 'string', input: 'string')]
    public function startJob(): AsyncJobHandler
    {
        return new AsyncJobHandler();
    }
}

final class AsyncJobHandler implements OperationHandlerInterface
{
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        // Deterministic-ish token derived from requestId so the caller can correlate.
        $token = 'job-' . \substr(\hash('sha1', $details->requestId . ':' . $param), 0, 12);
        return OperationStartResult::async(new OperationInfo($token, OperationState::Running));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {}
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
