<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Interceptor;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationCancelInput;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationStartInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\NexusOperationInboundCallsInterceptorTrait;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: a {@see NexusOperationInboundCallsInterceptor} registered on
 * the worker via {@see PipelineProvider} is invoked for every Nexus operation
 * dispatched to that worker, and may either short-circuit (auth) or observe
 * (logging) the call.
 *
 * Cross-process assertion strategy: the LoggingInterceptor records a marker in
 * a worker-local static; the handler reads that marker and embeds it in the
 * response payload, so the test (which runs in a different process) can
 * observe the interceptor's effect via the HTTP response body alone.
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
        #[Stub('Extra_Nexus_Interceptor_Bootstrap')] WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Interceptor',
            'test-nexus-interceptor',
        );

        [$code, $body] = $helper->postOperation(
            $endpointId,
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
        #[Stub('Extra_Nexus_Interceptor_Bootstrap')] WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Interceptor',
            'test-nexus-interceptor',
        );

        [$code, $body] = $helper->postOperation(
            $endpointId,
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
}

/**
 * Worker-local marker shared between the LoggingInterceptor (writer) and the
 * GreetingServiceImpl (reader). Both run in the same RoadRunner worker
 * process, so a process-local static is sufficient — the assertions in the
 * PHPUnit process see the marker indirectly via the HTTP response payload.
 */
final class WorkerLocalMarker
{
    public static ?string $lastSeen = null;
}

final class AuthInterceptor implements NexusOperationInboundCallsInterceptor
{
    public const AUTH_HEADER = 'authorization';

    public function __construct(private readonly string $authToken) {}

    public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult
    {
        $this->assertAuthorized($input->context->headers[self::AUTH_HEADER] ?? null);
        return $next($input);
    }

    public function cancelNexusOperation(NexusOperationCancelInput $input, callable $next): void
    {
        $this->assertAuthorized($input->context->headers[self::AUTH_HEADER] ?? null);
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

    public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult
    {
        WorkerLocalMarker::$lastSeen = "seen-by-interceptor:{$input->context->operation}";
        return $next($input);
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

#[Service(name: 'GreetingService')]
interface GreetingServiceInterface
{
    #[Operation]
    public function sayHello(string $name): string;
}

class GreetingServiceImpl implements GreetingServiceInterface
{
    public function sayHello(string $name): string
    {
        $marker = WorkerLocalMarker::$lastSeen ?? 'no-marker';
        return "Hello, {$name}! [{$marker}]";
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
