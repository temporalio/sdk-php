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
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHttpClient;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * {@see NexusOperationInboundCallsInterceptor} registered via {@see PipelineProvider};
 * interceptors record markers in {@see WorkerLocalMarker} that handlers/tests read back.
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
        return NexusWorkerOptions::default();
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
        // Greeting proves the pipeline reached the handler.
        self::assertStringContainsString('Hello, World!', $body);
        // Marker proves the LoggingInterceptor ran before the handler.
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

        // Nexus maps `ErrorType::Unauthorized` to HTTP 403.
        self::assertSame(403, $code, "Expected 403, got {$code}. Response: {$body}");
        // Auth interceptor short-circuited the pipeline — no handler greeting in the body.
        self::assertStringNotContainsString('Hello, World!', $body);
    }

    /** Caller cancels an async op mid-flight; the cancel-side interceptor must leave its marker. */
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

        // File-backed marker survives cross-process dispatch between RR workers.
        $marker = WorkerLocalMarker::readCancel();
        self::assertNotNull($marker, 'Cancel interceptor never wrote its marker.');
        self::assertStringContainsString('cancel-seen-by-interceptor', $marker);
        WorkerLocalMarker::clearCancel();
    }
}

/**
 * Markers shared between interceptors (writers) and downstream readers:
 * $lastSeen is process-local (sync case); the cancel marker is file-backed (cross-process).
 */
final class WorkerLocalMarker
{
    public const CANCEL_MARKER_FILE = '/tmp/nexus-interceptor-cancel-marker-test-nexus-interceptor-cancel';

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
        // Auth is scoped to GreetingService; workflow-to-workflow stubs carry no auth header.
        if ($input->operationContext->service === 'GreetingService') {
            $this->assertAuthorized($input->operationContext->headers->get(self::AUTH_HEADER));
        }
        return $next($input);
    }

    public function cancelOperation(CancelOperationInput $input, callable $next): void
    {
        if ($input->operationContext->service === 'GreetingService') {
            $this->assertAuthorized($input->operationContext->headers->get(self::AUTH_HEADER));
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
    public function longRunning(string $handlerWorkflowId): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            CancelHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId($handlerWorkflowId),
            $handlerWorkflowId,
        );
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
            // Marker isn't read here: handler may run in a different RR worker process.
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
        yield Workflow::timer(CarbonInterval::seconds(NexusWorkerOptions::PRE_CANCEL_TIMER_SECONDS));
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
