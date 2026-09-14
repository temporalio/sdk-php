<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Common\PayloadLimitOptions;
use Temporal\Client\WorkflowClient;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\Interceptor\Pipeline;

final class PayloadLimitsTestCase extends TestCase
{
    /** @var array<array-key, array{string, array}> */
    private array $records = [];

    public function testWarningIsLoggedOnRpcCall(): void
    {
        $client = $this->createClient()->withPayloadLimits(
            new PayloadLimitOptions(1024, 1024),
            $this->createLogger(),
        );

        $client->testCall($this->request(2000));

        self::assertCount(1, $this->records);
        self::assertStringContainsString('[TMPRL1103]', $this->records[0][0]);
        self::assertSame('testCall', $this->records[0][1]['method']);
    }

    public function testNoWarningWithoutLimits(): void
    {
        $client = $this->createClient();

        $client->testCall($this->request(2000));

        self::assertSame([], $this->records);
    }

    public function testLimitsCanBeDisabled(): void
    {
        $client = $this->createClient()->withPayloadLimits(
            PayloadLimitOptions::disabled(),
            $this->createLogger(),
        );

        $client->testCall($this->request(2000));

        self::assertSame([], $this->records);
    }

    public function testWarningsCanBeTurnedOff(): void
    {
        $client = $this->createClient()
            ->withPayloadLimits(new PayloadLimitOptions(1024, 1024), $this->createLogger())
            ->withPayloadLimits(PayloadLimitOptions::disabled(), $this->createLogger());

        $client->testCall($this->request(2000));

        self::assertSame([], $this->records);
    }

    public function testWarnsWhenTheInterceptorPipelineIsInstalledFirst(): void
    {
        // `withInterceptorPipeline()` captures a callable bound to the client it was called on,
        // so the limits must not depend on the order the two are applied in.
        $client = $this->createClient()
            ->withInterceptorPipeline(Pipeline::prepare([$this->passThroughInterceptor()]))
            ->withPayloadLimits(new PayloadLimitOptions(1024, 1024), $this->createLogger());

        $client->testCall($this->request(2000));

        self::assertCount(1, $this->records);
    }

    public function testWarnsWhenTheLimitsAreInstalledFirst(): void
    {
        $client = $this->createClient()
            ->withPayloadLimits(new PayloadLimitOptions(1024, 1024), $this->createLogger())
            ->withInterceptorPipeline(Pipeline::prepare([$this->passThroughInterceptor()]));

        $client->testCall($this->request(2000));

        self::assertCount(1, $this->records);
    }

    public function testWorkflowClientEnablesWarnings(): void
    {
        $client = new WorkflowClient(
            $this->createClient(),
            (new ClientOptions())->withPayloadLimits(new PayloadLimitOptions(1024, 1024)),
            logger: $this->createLogger(),
        );

        $serviceClient = $this->serviceClientOf($client);
        $serviceClient->testCall($this->request(2000));

        self::assertCount(1, $this->records);
        self::assertStringContainsString('[TMPRL1103]', $this->records[0][0]);
    }

    public function testWorkflowClientRespectsDisabledLimits(): void
    {
        $client = new WorkflowClient(
            $this->createClient(),
            (new ClientOptions())->withPayloadLimits(PayloadLimitOptions::disabled()),
            logger: $this->createLogger(),
        );

        $serviceClient = $this->serviceClientOf($client);
        // Larger than the default limit: only the disabled options can keep it silent
        $serviceClient->testCall($this->request(1024 * 1024));

        self::assertSame([], $this->records);
    }

    public function testWorkflowClientWarnsWithTheDefaultLimits(): void
    {
        // The limits are not configured: the default ones must still be in force
        $client = new WorkflowClient($this->createClient(), logger: $this->createLogger());

        $serviceClient = $this->serviceClientOf($client);
        $serviceClient->testCall($this->request(PayloadLimitOptions::DEFAULT_PAYLOAD_SIZE_WARNING + 1));

        self::assertCount(1, $this->records);
        self::assertStringContainsString('[TMPRL1103]', $this->records[0][0]);
    }

    public function testWorkflowClientKeepsSilentBelowTheDefaultLimits(): void
    {
        $client = new WorkflowClient($this->createClient(), logger: $this->createLogger());

        $serviceClient = $this->serviceClientOf($client);
        $serviceClient->testCall($this->request(1024));

        self::assertSame([], $this->records);
    }

    public function testRetriedCallIsMeasuredOnce(): void
    {
        // The check runs before the call, so the retries of one RPC do not multiply the warnings
        $client = $this->createClient(failures: 2)->withPayloadLimits(
            new PayloadLimitOptions(1024, 1024),
            $this->createLogger(),
        );

        $client->testCall($this->request(2000));

        self::assertCount(1, $this->records);
    }

    public function testExplicitLimitsOfTheServiceClientSurviveTheClient(): void
    {
        // The Client must not override what the service client was configured with
        $client = new WorkflowClient(
            $this->createClient()->withPayloadLimits(PayloadLimitOptions::disabled(), $this->createLogger()),
        );

        $serviceClient = $this->serviceClientOf($client);
        $serviceClient->testCall($this->request(1024 * 1024));

        self::assertSame([], $this->records);
    }

    public function testExplicitLoggerOfTheServiceClientSurvivesTheClient(): void
    {
        $serviceClient = $this->createClient()
            ->withPayloadLimits(new PayloadLimitOptions(1024, 1024), $this->createLogger());

        $client = new WorkflowClient($serviceClient);

        $serviceClient = $this->serviceClientOf($client);
        $serviceClient->testCall($this->request(2000));

        // The limit of the service client, not the 512 KiB default of the Client
        self::assertCount(1, $this->records);
    }

    public function testClientIsImmutable(): void
    {
        $client = $this->createClient();

        $result = $client->withPayloadLimits(new PayloadLimitOptions(1024, 1024), $this->createLogger());

        self::assertNotSame($client, $result);

        $client->testCall($this->request(2000));

        self::assertSame([], $this->records, 'The original client is not affected.');
    }

    protected function setUp(): void
    {
        $this->records = [];
        parent::setUp();
    }

    private function passThroughInterceptor(): GrpcClientInterceptor
    {
        return new class implements GrpcClientInterceptor {
            public function interceptCall(
                string $method,
                object $arg,
                ContextInterface $ctx,
                callable $next,
            ): object {
                return $next($method, $arg, $ctx);
            }
        };
    }

    /**
     * The Client hands back the very service client it was given, with the limits applied to it.
     *
     * @return ServiceClient&object{testCall: callable}
     */
    private function serviceClientOf(WorkflowClient $client): object
    {
        $serviceClient = $client->getServiceClient();

        self::assertInstanceOf($this->createClient()::class, $serviceClient);

        return $serviceClient;
    }

    private function request(int $size): StartWorkflowExecutionRequest
    {
        return (new StartWorkflowExecutionRequest())->setInput(
            new Payloads(['payloads' => [(new Payload())->setData(\str_repeat('x', $size))]]),
        );
    }

    private function createLogger(): AbstractLogger
    {
        return new class($this->records) extends AbstractLogger {
            public function __construct(private array &$records) {}

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $message, $context];
            }
        };
    }

    /**
     * @param int<0, max> $failures Number of retryable failures before the call succeeds.
     */
    private function createClient(int $failures = 0): ServiceClient
    {
        $stub = static fn() => new class($failures) extends WorkflowServiceClient {
            public function __construct(private int $failures = 0) {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            /**
             * Stands for a real RPC method: returns a successful response without any IO.
             */
            public function testCall(object $arg, array $metadata = [], array $options = []): object
            {
                $code = $this->failures-- > 0 ? StatusCode::UNAVAILABLE : 0;

                return new class($code) {
                    public function __construct(private int $code) {}

                    public function wait(): array
                    {
                        return [(object) ['result' => true], (object) ['code' => $this->code, 'details' => '']];
                    }
                };
            }

            public function close(): void {}
        };

        return new class($stub) extends ServiceClient {
            public function testCall(object $request): mixed
            {
                return $this->invoke('testCall', $request, null);
            }
        };
    }
}
