<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse\Capabilities;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\Common\RpcRetryOptions;
use Temporal\Client\GRPC\BaseClient;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Internal\Interceptor\Pipeline;

class BaseClientTestCase extends TestCase
{
    public function testGetCapabilitiesUsesCache(): void
    {
        $client = $this->createClientMock();

        $capabilities0 = $client->getServerCapabilities();
        $capabilities1 = $client->getServerCapabilities();

        $this->assertTrue($capabilities0->supportsSchedules);
        $this->assertSame($capabilities0, $capabilities1);
    }

    public function testGetCapabilitiesClearsCache(): void
    {
        $client = $this->createClientMock();

        $capabilities0 = $client->getServerCapabilities();
        $client->getConnection()->disconnect();
        $capabilities1 = $client->getServerCapabilities();

        $this->assertTrue($capabilities0->supportsSchedules);
        $this->assertNotSame($capabilities0, $capabilities1);
    }

    public function testClose(): void
    {
        $client = $this->createClientMock(static fn() => new class extends WorkflowServiceClient {
            public function __construct()
            {
            }

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::TransientFailure->value;
            }

            public function close(): void
            {
            }
        });
        $client->close();

        $this->assertFalse($client->getConnection()->isConnected());
    }

    public function testGetContext(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext();

        $this->assertSame($context, $client->getContext());
    }

    public function testWithContext(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext();
        $context2 = $context->withTimeout(1.234);
        $client2 = $client->withContext($context2);

        $this->assertSame($context, $client->getContext());
        $this->assertSame($context2, $client2->getContext());
        $this->assertNotSame($client, $client2);
    }

    public function testWithTimeoutDynamicDeadline(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext()->withTimeout(1.234);

        $this->assertNotSame($context->getDeadline(), $context->getDeadline());
    }

    public function testContextGetDeadlineWithoutDeadline(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext();

        $this->assertNull($context->getDeadline());
    }

    public function testContextGetDeadlineWithStaticDeadline(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext()->withDeadline(new DateTimeImmutable('+1 second'));

        $this->assertSame($context->getDeadline(), $context->getDeadline());
    }

    public function testWithAuthKey(): void
    {
        $client = $this->createClientMock();
        $context = $client->getContext();
        $client2 = $client->withAuthKey('test-key');

        // Client immutability
        $this->assertNotSame($client, $client2);
        // Old context was not modified
        $this->assertSame($context, $client->getContext());
        // New context is the same as the old one
        // because the auth key is added to the context before API method call
        $this->assertSame($context, $client2->getContext());

        $ctx1 = $client->testCall()->ctx;
        self::assertInstanceOf(ContextInterface::class, $ctx1);
        $this->assertArrayNotHasKey('Authorization', $ctx1->getMetadata());
        $keysBefore = \count($ctx1->getMetadata());

        $ctx2 = $client2->testCall()->ctx;
        self::assertInstanceOf(ContextInterface::class, $ctx2);
        $this->assertArrayHasKey('Authorization', $ctx2->getMetadata());
        $this->assertSame(['Bearer test-key'], $ctx2->getMetadata()['Authorization']);
        $this->assertSame($keysBefore + 1, \count($ctx2->getMetadata()), 'API Key doesnt affect other metadata');
    }

    public function testWithDynamicAuthKey(): void
    {
        $client = $this->createClientMock()->withAuthKey(new class implements \Stringable {
            public function __toString(): string
            {
                static $counter = 0;
                $counter++;
                return "test-key-$counter";
            }
        });

        $ctx = $client->testCall()->ctx;
        self::assertInstanceOf(ContextInterface::class, $ctx);
        $this->assertArrayHasKey('Authorization', $ctx->getMetadata());
        $this->assertSame(['Bearer test-key-1'], $ctx->getMetadata()['Authorization']);

        $ctx2 = $client->testCall()->ctx;
        self::assertInstanceOf(ContextInterface::class, $ctx2);
        $this->assertArrayHasKey('Authorization', $ctx2->getMetadata());
        $this->assertSame(['Bearer test-key-2'], $ctx2->getMetadata()['Authorization']);
    }

    public function testServiceClientCallDeadlineReached(): void
    {
        $client = $this->createClientMock(fn() => new class() extends WorkflowServiceClient {
            public function __construct() {}
            public function testCall()
            {
                throw new class((object)['code' => StatusCode::UNKNOWN, 'metadata' => []])
                    extends ServiceClientException {
                };
            }
            public function close(): void
            {
            }
        })->withInterceptorPipeline(null);

        $client = $client->withContext($client->getContext()
            ->withDeadline(new DateTimeImmutable('-1 second'))
            ->withRetryOptions(RpcRetryOptions::new()->withMaximumAttempts(2)) // stop if deadline doesn't work
        );

        self::expectException(TimeoutException::class);

        $client->testCall();
    }

    public function testServiceClientCallCustomException(): void
    {
        $client = $this->createClientMock(fn() => new class() extends WorkflowServiceClient {
            public function __construct() {}
            public function testCall()
            {
                throw new \RuntimeException('foo');
            }
            public function close(): void
            {
            }
        })->withInterceptorPipeline(null);

        $client = $client->withContext($client->getContext()
            ->withDeadline(new DateTimeImmutable('-1 second'))
            ->withRetryOptions(RpcRetryOptions::new()->withMaximumAttempts(2)) // stop if deadline doesn't work
        );

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('foo');

        $client->testCall();
    }

    /**
     * After attempts are exhausted, the last error is thrown.
     */
    public function testServiceClientCallMaximumAttemptsReached(): void
    {
        $client = $this->createClientMock(fn() => new class() extends WorkflowServiceClient {
            public function __construct() {}
            public function testCall()
            {
                static $counter = 0;
                throw new class(++$counter)
                    extends ServiceClientException {
                    public function __construct(public int $attempt)
                    {
                        parent::__construct((object)['code' => StatusCode::UNKNOWN, 'metadata' => []]);
                    }
                    public function isTestError(): bool
                    {
                        return true;
                    }
                };
            }
            public function close(): void
            {
            }
        })->withInterceptorPipeline(null);

        $client = $client->withContext($client->getContext()
            ->withDeadline(new DateTimeImmutable('+2 seconds')) // stop if attempts don't work
            ->withRetryOptions(RpcRetryOptions::new()->withMaximumAttempts(3)->withBackoffCoefficient(1))
        );

        try {
            $client->testCall();
            self::fail('Expected exception');
        } catch (ServiceClientException $e) {
            self::assertTrue($e->isTestError());
            self::assertSame(3, $e->attempt);
        }
    }

    private function createClientMock(?callable $serviceClientFactory = null): BaseClient
    {
        return (new class($serviceClientFactory ?? static fn() => new class extends WorkflowServiceClient {
            public function __construct()
            {
            }

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            public function close(): void
            {
            }
        }) extends ServiceClient {
            public function getSystemInfo(
                GetSystemInfoRequest $arg,
                ContextInterface $ctx = null,
            ): GetSystemInfoResponse {
                return (new GetSystemInfoResponse())
                    ->setCapabilities((new Capabilities)->setSupportsSchedules(true))
                    ->setServerVersion('1.2.3');
            }

            public function testCall(): mixed
            {
                return $this->invoke("testCall", (object)[], null);
            }
        })->withInterceptorPipeline(
            Pipeline::prepare([new class implements \Temporal\Interceptor\GrpcClientInterceptor {
                public function interceptCall(
                    string $method,
                    object $arg,
                    ContextInterface $ctx,
                    callable $next
                ): object {
                    return (object)['method' => $method, 'arg' => $arg, 'ctx' => $ctx, 'next' => $next];
                }
            }]),
        );
    }
}
