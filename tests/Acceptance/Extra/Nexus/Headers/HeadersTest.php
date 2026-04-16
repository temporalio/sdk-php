<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Headers;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
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
 * Acceptance test: HTTP request headers are propagated to the Nexus handler.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class HeadersTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function customHeadersArePropagated(
        State $state,
        #[Stub('Extra_Nexus_Headers_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $taskQueue = 'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Headers';
        $endpointName = NexusHelper::uniqueEndpointName('test-nexus-hdr');

        if (!NexusHelper::createEndpoint($endpointName, $state->namespace, $taskQueue, $state->address)) {
            self::markTestSkipped('Could not create Nexus endpoint');
        }

        $endpointId = NexusHelper::getEndpointId($endpointName, $state->address);
        if ($endpointId === null) {
            self::markTestSkipped('Could not resolve endpoint UUID');
        }

        $host = \parse_url("http://{$state->address}", PHP_URL_HOST) ?? '127.0.0.1';

        [$code, $resp] = NexusHelper::postNexus(
            $host,
            $endpointId,
            'HeaderEchoService',
            'echoHeader',
            'X-Custom-Header',
            ['X-Custom-Header' => 'my-test-value'],
        );

        self::assertSame(200, $code, "Expected 200, got {$code}. Response: {$resp}");
        self::assertStringContainsString('my-test-value', (string) $resp, 'Expected header value in response');
    }
}

#[Service(name: 'HeaderEchoService')]
interface HeaderEchoServiceInterface
{
    #[Operation]
    public function echoHeader(string $headerName): string;
}

#[ServiceImpl(service: HeaderEchoServiceInterface::class)]
class HeaderEchoServiceImpl
{
    #[OperationImpl]
    public function echoHeader(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static function (OperationContext $ctx, OperationStartDetails $details, ?string $headerName): string {
                if ($headerName === null) {
                    return '';
                }
                // Headers in OperationContext are case-insensitive (lowercased)
                $key = \strtolower($headerName);
                return $ctx->headers[$key] ?? "missing:{$headerName}";
            },
        );
    }
}

#[WorkflowInterface]
class HeadersBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Headers_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
