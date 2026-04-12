<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Operatorservice\V1;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient as ApiOperatorServiceClient;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\OperatorClient;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Tests\TestCase;

#[CoversClass(OperatorClient::class)]
final class OperatorClientTestCase extends TestCase
{
    public function testDeleteNamespaceUsesSdkOperatorClient(): void
    {
        $captured = new class {
            public ?string $method = null;
            public ?V1\DeleteNamespaceRequest $request = null;
            public ?ContextInterface $context = null;
        };

        $client = (new OperatorClient(static fn() => new class extends ApiOperatorServiceClient {
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
                    $this->captured->request = $arg;
                    $this->captured->context = $ctx;

                    return (new V1\DeleteNamespaceResponse())->setDeletedNamespace('temporal-system-deleted');
                }
            }]),
        )->withAuthKey('test-key');

        $response = $client->DeleteNamespace((new V1\DeleteNamespaceRequest())->setNamespace('test-namespace'));

        self::assertSame('DeleteNamespace', $captured->method);
        self::assertSame('test-namespace', $captured->request?->getNamespace());
        self::assertSame(['Bearer test-key'], $captured->context?->getMetadata()['Authorization'] ?? null);
        self::assertSame('temporal-system-deleted', $response->getDeletedNamespace());
    }

    #[Test]
    public function addSearchAttributes(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\AddSearchAttributesResponse(),
        );

        $client->AddSearchAttributes(new V1\AddSearchAttributesRequest());

        self::assertSame('AddSearchAttributes', $captured->method);
        self::assertInstanceOf(V1\AddSearchAttributesRequest::class, $captured->arg);
    }

    #[Test]
    public function removeSearchAttributes(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\RemoveSearchAttributesResponse(),
        );

        $client->RemoveSearchAttributes(new V1\RemoveSearchAttributesRequest());

        self::assertSame('RemoveSearchAttributes', $captured->method);
        self::assertInstanceOf(V1\RemoveSearchAttributesRequest::class, $captured->arg);
    }

    #[Test]
    public function listSearchAttributes(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListSearchAttributesResponse(),
        );

        $client->ListSearchAttributes(new V1\ListSearchAttributesRequest());

        self::assertSame('ListSearchAttributes', $captured->method);
        self::assertInstanceOf(V1\ListSearchAttributesRequest::class, $captured->arg);
    }

    #[Test]
    public function addOrUpdateRemoteCluster(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\AddOrUpdateRemoteClusterResponse(),
        );

        $client->AddOrUpdateRemoteCluster(new V1\AddOrUpdateRemoteClusterRequest());

        self::assertSame('AddOrUpdateRemoteCluster', $captured->method);
        self::assertInstanceOf(V1\AddOrUpdateRemoteClusterRequest::class, $captured->arg);
    }

    #[Test]
    public function removeRemoteCluster(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\RemoveRemoteClusterResponse(),
        );

        $client->RemoveRemoteCluster(new V1\RemoveRemoteClusterRequest());

        self::assertSame('RemoveRemoteCluster', $captured->method);
        self::assertInstanceOf(V1\RemoveRemoteClusterRequest::class, $captured->arg);
    }

    #[Test]
    public function listClusters(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        $client->ListClusters(new V1\ListClustersRequest());

        self::assertSame('ListClusters', $captured->method);
        self::assertInstanceOf(V1\ListClustersRequest::class, $captured->arg);
    }

    #[Test]
    public function getNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\GetNexusEndpointResponse(),
        );

        $client->GetNexusEndpoint(new V1\GetNexusEndpointRequest());

        self::assertSame('GetNexusEndpoint', $captured->method);
        self::assertInstanceOf(V1\GetNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function createNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\CreateNexusEndpointResponse(),
        );

        $client->CreateNexusEndpoint(new V1\CreateNexusEndpointRequest());

        self::assertSame('CreateNexusEndpoint', $captured->method);
        self::assertInstanceOf(V1\CreateNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function updateNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\UpdateNexusEndpointResponse(),
        );

        $client->UpdateNexusEndpoint(new V1\UpdateNexusEndpointRequest());

        self::assertSame('UpdateNexusEndpoint', $captured->method);
        self::assertInstanceOf(V1\UpdateNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function deleteNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\DeleteNexusEndpointResponse(),
        );

        $client->DeleteNexusEndpoint(new V1\DeleteNexusEndpointRequest());

        self::assertSame('DeleteNexusEndpoint', $captured->method);
        self::assertInstanceOf(V1\DeleteNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function listNexusEndpoints(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListNexusEndpointsResponse(),
        );

        $client->ListNexusEndpoints(new V1\ListNexusEndpointsRequest());

        self::assertSame('ListNexusEndpoints', $captured->method);
        self::assertInstanceOf(V1\ListNexusEndpointsRequest::class, $captured->arg);
    }

    #[Test]
    public function customContextIsPassedToInterceptor(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        $ctx = $client->getContext()->withMetadata(['x-custom' => ['value']]);
        $client->ListClusters(new V1\ListClustersRequest(), $ctx);

        self::assertSame(['value'], $captured->ctx->getMetadata()['x-custom'] ?? null);
    }

    #[Test]
    public function authKeyIsInjectedIntoContext(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        $client = $client->withAuthKey('operator-key');
        $client->ListClusters(new V1\ListClustersRequest());

        self::assertSame(['Bearer operator-key'], $captured->ctx->getMetadata()['Authorization'] ?? null);
    }

    #[Test]
    public function withoutAuthKeyNoAuthorizationHeader(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        $client->ListClusters(new V1\ListClustersRequest());

        self::assertArrayNotHasKey('Authorization', $captured->ctx->getMetadata());
    }

    #[Test]
    public function withContextReturnsNewImmutableInstance(): void
    {
        [, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        $ctx = $client->getContext()->withMetadata(['foo' => ['bar']]);
        $client2 = $client->withContext($ctx);

        self::assertNotSame($client, $client2);
        self::assertSame($ctx, $client2->getContext());
        self::assertNotSame($ctx, $client->getContext());
    }

    #[Test]
    public function withAuthKeyReturnsNewImmutableInstance(): void
    {
        [, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        $client2 = $client->withAuthKey('key');

        self::assertNotSame($client, $client2);
    }

    #[Test]
    public function closeDisconnectsConnection(): void
    {
        $client = (new OperatorClient(static fn() => new class extends ApiOperatorServiceClient {
            public function __construct() {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::TransientFailure->value;
            }

            public function close(): void {}
        }));

        $client->close();

        self::assertFalse($client->getConnection()->isConnected());
    }

    #[Test]
    public function implementsGrpcClientInterface(): void
    {
        [, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        self::assertInstanceOf(\Temporal\Client\GRPC\GrpcClientInterface::class, $client);
    }

    #[Test]
    public function implementsOperatorClientInterface(): void
    {
        [, $client] = $this->createInterceptedClient(
            static fn() => new V1\ListClustersResponse(),
        );

        self::assertInstanceOf(\Temporal\Client\GRPC\OperatorClientInterface::class, $client);
    }

    /**
     * @return array{object, OperatorClient}
     */
    private function createInterceptedClient(\Closure $responseFactory): array
    {
        $captured = new class {
            public ?string $method = null;
            public ?object $arg = null;
            public ?ContextInterface $ctx = null;
        };

        $client = (new OperatorClient(static fn() => new class extends ApiOperatorServiceClient {
            public function __construct() {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            public function close(): void {}
        }))->withInterceptorPipeline(
            Pipeline::prepare([new class($captured, $responseFactory) implements GrpcClientInterceptor {
                public function __construct(
                    private readonly object $captured,
                    private readonly \Closure $responseFactory,
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

                    return ($this->responseFactory)();
                }
            }]),
        );

        return [$captured, $client];
    }
}
