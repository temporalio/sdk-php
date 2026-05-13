<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Idempotency;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Two HTTP starts with the same Nexus-Request-Id must yield identical operation tokens. */
#[Worker(options: [self::class, 'workerOptions'])]
class RequestIdIdempotencyTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function sameNexusRequestIdYieldsSameOperationToken(
        State $state,
        #[Stub('Extra_Nexus_Idempotency_Bootstrap')]
        WorkflowStubInterface $bootstrapStub,
    ): void {
        $bootstrapStub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            __NAMESPACE__,
            'nexus-idempotent',
        );

        $sharedRequestId = 'idempotency-test-' . \bin2hex(\random_bytes(4));

        $extraHeaders = [
            'Nexus-Callback-Url' => 'http://callback.example.local/done',
            'Nexus-Request-Id' => $sharedRequestId,
        ];

        [$code1, $body1] = $helper->postOperation(
            $endpointId,
            'IdempotentWorkflowService',
            'longRun',
            'payload-1',
            $extraHeaders,
        );
        self::assertSame(201, $code1, "First async start expected 201, got {$code1}. Body: {$body1}");
        $token1 = self::extractOperationToken($body1);

        [$code2, $body2] = $helper->postOperation(
            $endpointId,
            'IdempotentWorkflowService',
            'longRun',
            'payload-2',
            $extraHeaders,
        );
        self::assertSame(201, $code2, "Second async start expected 201, got {$code2}. Body: {$body2}");
        $token2 = self::extractOperationToken($body2);

        self::assertSame(
            $token1,
            $token2,
            "Same Nexus-Request-Id must yield identical operation tokens. token1={$token1} token2={$token2}",
        );
    }

    #[Test]
    public function differentNexusRequestIdsYieldDifferentOperationTokens(
        State $state,
        #[Stub('Extra_Nexus_Idempotency_Bootstrap')]
        WorkflowStubInterface $bootstrapStub,
    ): void {
        $bootstrapStub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            __NAMESPACE__,
            'nexus-idempotent-distinct',
        );

        $requestIdA = 'idempotency-test-A-' . \bin2hex(\random_bytes(4));
        $requestIdB = 'idempotency-test-B-' . \bin2hex(\random_bytes(4));

        [$code1, $body1] = $helper->postOperation(
            $endpointId,
            'IdempotentWorkflowService',
            'longRun',
            'payload-1',
            [
                'Nexus-Callback-Url' => 'http://callback.example.local/done',
                'Nexus-Request-Id' => $requestIdA,
            ],
        );
        self::assertSame(201, $code1);
        $tokenA = self::extractOperationToken($body1);

        [$code2, $body2] = $helper->postOperation(
            $endpointId,
            'IdempotentWorkflowService',
            'longRun',
            'payload-1',
            [
                'Nexus-Callback-Url' => 'http://callback.example.local/done',
                'Nexus-Request-Id' => $requestIdB,
            ],
        );
        self::assertSame(201, $code2);
        $tokenB = self::extractOperationToken($body2);

        self::assertNotSame(
            $tokenA,
            $tokenB,
            'Different Nexus-Request-Ids must yield distinct operation tokens (no accidental cross-request dedup).',
        );
    }

    private static function extractOperationToken(string $responseBody): string
    {
        $decoded = \json_decode($responseBody, true);
        self::assertIsArray($decoded, "Async start body must be JSON OperationInfo. Body: {$responseBody}");
        $token = $decoded['token'] ?? $decoded['operationToken'] ?? null;
        self::assertIsString($token, "Async start body must carry a token field. Body: {$responseBody}");
        self::assertNotSame('', $token, 'Operation token must be non-empty.');
        return $token;
    }
}

#[Service(name: 'IdempotentWorkflowService')]
class IdempotentWorkflowService
{
    #[AsyncOperation(output: 'string')]
    public function longRun(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                IdempotentHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'longRun')]
    public function cancelLongRun(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class IdempotentHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Idempotency_Handler')]
    public function handle(string $input)
    {
        yield Workflow::timer(CarbonInterval::seconds(3));
        return 'done:' . $input;
    }
}

#[WorkflowInterface]
class IdempotencyBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Idempotency_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
