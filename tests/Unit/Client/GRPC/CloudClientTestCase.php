<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Cloud\Cloudservice\V1\CloudServiceClient as ApiCloudServiceClient;
use Temporal\Api\Cloud\Cloudservice\V1\GetUsersRequest;
use Temporal\Api\Cloud\Cloudservice\V1\GetUsersResponse;
use Temporal\Client\GRPC\CloudClient;
use Temporal\Client\GRPC\CloudClientInterface;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\GrpcClientInterface;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Tests\TestCase;

#[CoversClass(CloudClient::class)]
final class CloudClientTestCase extends TestCase
{
    #[Test]
    public function rpcIsDispatchedByMethodName(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $response = $client->GetUsers((new GetUsersRequest())->setPageSize(10));

        self::assertSame('GetUsers', $captured->method);
        self::assertInstanceOf(GetUsersRequest::class, $captured->arg);
        self::assertSame(10, $captured->arg->getPageSize());
        self::assertSame('next-page', $response->getNextPageToken());
    }

    #[Test]
    public function apiVersionIsInjectedIntoContext(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $client->withApiVersion('2024-05-13-00')->GetUsers(new GetUsersRequest());

        self::assertSame(['2024-05-13-00'], $captured->ctx->getMetadata()['temporal-cloud-api-version'] ?? null);
    }

    #[Test]
    public function withoutApiVersionNoVersionHeader(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $client->GetUsers(new GetUsersRequest());

        self::assertArrayNotHasKey('temporal-cloud-api-version', $captured->ctx->getMetadata());
    }

    #[Test]
    public function apiVersionIsInjectedIntoPerCallContext(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $client->withApiVersion('2024-05-13-00')->GetUsers(new GetUsersRequest(), Context::default()->withTimeout(5));

        self::assertSame(['2024-05-13-00'], $captured->ctx->getMetadata()['temporal-cloud-api-version'] ?? null);
    }

    #[Test]
    public function apiVersionDoesNotOverrideExplicitContextHeader(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $client
            ->withContext($client->getContext()->withMetadata(['temporal-cloud-api-version' => ['explicit']]))
            ->withApiVersion('2024-05-13-00')
            ->GetUsers(new GetUsersRequest());

        self::assertSame(['explicit'], $captured->ctx->getMetadata()['temporal-cloud-api-version'] ?? null);
    }

    #[Test]
    public function apiVersionDoesNotOverrideExplicitPerCallHeader(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $ctx = Context::default()->withMetadata(['temporal-cloud-api-version' => ['explicit']]);
        $client->withApiVersion('2024-05-13-00')->GetUsers(new GetUsersRequest(), $ctx);

        self::assertSame(['explicit'], $captured->ctx->getMetadata()['temporal-cloud-api-version'] ?? null);
    }

    #[Test]
    public function apiVersionIsCombinedWithAuthKey(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $client->withApiVersion('2024-05-13-00')->withAuthKey('cloud-key')->GetUsers(new GetUsersRequest());

        self::assertSame(['2024-05-13-00'], $captured->ctx->getMetadata()['temporal-cloud-api-version'] ?? null);
        self::assertSame(['Bearer cloud-key'], $captured->ctx->getMetadata()['Authorization'] ?? null);
    }

    #[Test]
    public function withApiVersionReturnsNewImmutableInstance(): void
    {
        [$captured, $client] = $this->createInterceptedClient();

        $client2 = $client->withApiVersion('2024-05-13-00');
        $client->GetUsers(new GetUsersRequest());

        self::assertNotSame($client, $client2);
        self::assertArrayNotHasKey('temporal-cloud-api-version', $captured->ctx->getMetadata());
    }

    #[Test]
    public function implementsCloudClientInterface(): void
    {
        [, $client] = $this->createInterceptedClient();

        self::assertInstanceOf(CloudClientInterface::class, $client);
        self::assertInstanceOf(GrpcClientInterface::class, $client);
    }

    /**
     * @return array{object, CloudClient}
     */
    private function createInterceptedClient(): array
    {
        $captured = new class {
            public ?string $method = null;
            public ?object $arg = null;
            public ?ContextInterface $ctx = null;
        };

        $client = (new CloudClient(static fn() => new class extends ApiCloudServiceClient {
            public function __construct() {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            public function close(): void {}
        }))->withInterceptorPipeline(
            Pipeline::prepare([new class($captured) implements GrpcClientInterceptor {
                public function __construct(
                    private readonly object $captured,
                ) {}

                public function interceptCall(
                    string $method,
                    object $arg,
                    ContextInterface $ctx,
                    callable $next,
                ): object {
                    $this->captured->method = $method;
                    $this->captured->arg = $arg;
                    $this->captured->ctx = $ctx;

                    return (new GetUsersResponse())->setNextPageToken('next-page');
                }
            }]),
        );

        return [$captured, $client];
    }
}
