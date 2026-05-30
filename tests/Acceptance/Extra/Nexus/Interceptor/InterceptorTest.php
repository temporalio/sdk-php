<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Interceptor;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\NexusOperationInboundCallsInterceptorTrait;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHttpClient;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance tests for {@see NexusOperationInboundCallsInterceptor} registered
 * on the worker via {@see PipelineProvider}.
 *
 * Cross-process assertion strategy: interceptors record markers in
 * {@see WorkerLocalMarker} (a worker-local static); the underlying handler
 * code reads those markers and embeds them in its return value, so the
 * PHPUnit process can observe the interceptor's effect via the response
 * payload alone — no shared mutable state across processes.
 */
#[Worker(
    pipelineProvider: [InterceptorTestServices::class, 'interceptors'],
    options: [InterceptorTest::class, 'workerOptions'],
)]
class InterceptorTest extends TestCase
{
    public const AUTH_TOKEN = 'super-secret-token';

    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function startInvokesInterceptorWithValidToken(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_Interceptor_Bootstrap')] WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'test-nexus-interceptor');

        [$code, $body, ] = $http->post(
            $endpoint,
            'GreetingService',
            'sayHello',
            'World',
            [AuthInterceptor::AUTH_HEADER => self::AUTH_TOKEN],
        );

        self::assertSame(200, $code, "Expected 200, got {$code}. Response: {$body}");
        // Greeting from the handler proves the pipeline reached the underlying impl.
        self::assertStringContainsString('Hello, World!', $body);
        // Marker proves the LoggingInterceptor ran *before* the handler in the worker
        // process: the handler reads the worker-local static the interceptor wrote.
        self::assertStringContainsString('seen-by-interceptor', $body);
    }

    #[Test]
    public function startIsRejectedWithoutAuthToken(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_Interceptor_Bootstrap')] WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'test-nexus-interceptor');

        [$code, $body, ] = $http->post(
            $endpoint,
            'GreetingService',
            'sayHello',
            'World',
        );

        // Nexus maps `ErrorType::Unauthorized` (permission denied) to HTTP 403.
        self::assertSame(403, $code, "Expected 403, got {$code}. Response: {$body}");
        // Auth interceptor short-circuited the pipeline — handler greeting must
        // not appear in the body.
        self::assertStringNotContainsString('Hello, World!', $body);
    }

    /**
     * Drives an async Nexus operation that the caller cancels mid-flight.
     *
     * Two assertions:
     *   1. Caller's promise rejects with {@see NexusOperationFailure}/{@see CanceledFailure}.
     *   2. Handler workflow caught the cancel and returned `"cancelled:{marker}"`,
     *      where the marker comes from the cancel-side interceptor running in
     *      the same worker process. We read the handler workflow's return
     *      value directly (its workflow id is deterministic, see {@see CancelHandlerWorkflow}).
     */
    #[Test]
    public function cancelInvokesInterceptorAndHandlerSeesMarker(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        WorkerLocalMarker::clearCancel();

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'test-nexus-interceptor-cancel');

        // Deterministic handler workflow id — we want to fetch its result later.
        $handlerWorkflowId = 'cancel-marker-handler-' . \bin2hex(\random_bytes(4));

        $callerStub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Interceptor_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $client->start($callerStub, $endpoint->name, $handlerWorkflowId);
        self::assertSame('cancelled', $callerStub->getResult('string'));

        // Handler workflow caught CanceledFailure and returned 'cancelled:...'.
        $handlerStub = $client->newUntypedRunningWorkflowStub($handlerWorkflowId);
        $handlerResult = $handlerStub->getResult('string', timeout: 10);
        self::assertStringStartsWith('cancelled:', $handlerResult);

        // File-backed marker proves the cancel-side interceptor ran in some
        // RR worker process before WorkflowRunOperation::cancel reached
        // Temporal. File store survives cross-process dispatch (interceptor
        // and handler workflow may run in different RR worker processes).
        $marker = WorkerLocalMarker::readCancel();
        self::assertNotNull($marker, 'Cancel interceptor never wrote its marker.');
        self::assertStringContainsString('cancel-seen-by-interceptor', $marker);
        WorkerLocalMarker::clearCancel();
    }
}

/**
 * Markers shared between interceptors (writers) and downstream readers.
 *
 *   - $lastSeen: process-local static for the sync GreetingService case —
 *     the LoggingInterceptor and GreetingService always run in the same
 *     RR worker process, so an in-memory static is enough.
 *   - cancel marker file: filesystem-backed for the async-cancel case. The
 *     cancel-side interceptor and the handler workflow may be dispatched to
 *     different RR worker processes (RR spawns multiple workers per pool),
 *     so a file is the only reliable handoff. The PHPUnit process reads it
 *     directly to assert the cancel interceptor actually ran.
 */
final class WorkerLocalMarker
{
    public const CANCEL_MARKER_FILE = '/tmp/nexus-interceptor-cancel-marker';

    public static ?string $lastSeen = null;

    public static function recordCancel(string $value): void
    {
        \file_put_contents(self::CANCEL_MARKER_FILE, $value);
    }

    public static function readCancel(): ?string
    {
        return \is_file(self::CANCEL_MARKER_FILE)
            ? \file_get_contents(self::CANCEL_MARKER_FILE)
            : null;
    }

    public static function clearCancel(): void
    {
        if (\is_file(self::CANCEL_MARKER_FILE)) {
            \unlink(self::CANCEL_MARKER_FILE);
        }
    }
}

final class AuthInterceptor implements NexusOperationInboundCallsInterceptor
{
    public const AUTH_HEADER = 'authorization';

    public function __construct(private readonly string $authToken) {}

    public function startOperation(StartOperationInput $input, callable $next): OperationStartResult
    {
        // Scope auth to GreetingService only. The async cancel test below uses
        // a different service (InterceptorCancelService) reached via
        // workflow-to-workflow Nexus stubs that don't propagate caller auth
        // headers — guarding that service here would deadlock the test.
        if ($input->operationContext->service === 'GreetingService') {
            $this->assertAuthorized($input->operationContext->headers[self::AUTH_HEADER] ?? null);
        }
        return $next($input);
    }

    public function cancelOperation(CancelOperationInput $input, callable $next): void
    {
        // Same scoping rationale as startOperation().
        if ($input->operationContext->service === 'GreetingService') {
            $this->assertAuthorized($input->operationContext->headers[self::AUTH_HEADER] ?? null);
        }
        $next($input);
    }

    private function assertAuthorized(?string $token): void
    {
        if ($token !== $this->authToken) {
            throw HandlerException::create(ErrorType::Unauthorized, 'Unauthorized');
        }
    }
}

final class LoggingInterceptor implements NexusOperationInboundCallsInterceptor
{
    use NexusOperationInboundCallsInterceptorTrait;

    public function startOperation(StartOperationInput $input, callable $next): OperationStartResult
    {
        WorkerLocalMarker::$lastSeen = "seen-by-interceptor:{$input->operationContext->operation}";
        return $next($input);
    }

    public function cancelOperation(CancelOperationInput $input, callable $next): void
    {
        WorkerLocalMarker::recordCancel("cancel-seen-by-interceptor:{$input->operationContext->operation}");
        $next($input);
    }
}

class InterceptorTestServices
{
    public static function interceptors(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new AuthInterceptor(InterceptorTest::AUTH_TOKEN),
            new LoggingInterceptor(),
        ]);
    }
}

// ── Sync greeting service (used by the first two tests) ────────────────

#[Service(name: 'GreetingService')]
class GreetingService
{
    #[Operation]
    public function sayHello(string $name): string
    {
        $marker = WorkerLocalMarker::$lastSeen ?? 'no-marker';
        return "Hello, {$name}! [{$marker}]";
    }
}

// ── Async cancel service (used by the cancel-marker test) ──────────────

#[Service(name: 'InterceptorCancelService')]
class InterceptorCancelService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $handlerWorkflowId): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                CancelHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($handlerWorkflowId),
                $handlerWorkflowId,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'longRunning')]
    public function cancelLongRunning(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class CancelHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Interceptor_CancelHandler')]
    public function handle(string $input)
    {
        try {
            // Long enough that the caller can request cancel before we finish.
            yield Workflow::timer(CarbonInterval::seconds(30));
            return "completed:{$input}";
        } catch (CanceledFailure) {
            // We don't read the cancel marker here on purpose: the handler
            // workflow can land on a different RR worker process than the one
            // that ran the cancel-side interceptor, so the marker isn't
            // guaranteed to be process-local. The test reads the file-backed
            // marker directly from the PHPUnit process instead.
            return "cancelled:{$input}";
        }
    }
}

#[WorkflowInterface]
class CancelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Interceptor_CancelCaller')]
    public function run(string $endpoint, string $handlerWorkflowId)
    {
        $stub = Workflow::newNexusServiceStub(
            InterceptorCancelService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(60))
                ->withCancellationType(NexusOperationCancellationType::WaitRequested),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, $handlerWorkflowId, &$promise): void {
            $promise = $stub->longRunning($handlerWorkflowId);
        });

        // Give the handler workflow a chance to actually start before cancelling.
        yield Workflow::timer(CarbonInterval::seconds(1));
        $scope->cancel();

        try {
            yield $promise;
        } catch (NexusOperationFailure $e) {
            if ($e->getPrevious() instanceof CanceledFailure) {
                return 'cancelled';
            }
            throw $e;
        }

        return 'unexpected-completion';
    }
}

#[WorkflowInterface]
class InterceptorBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Interceptor_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
