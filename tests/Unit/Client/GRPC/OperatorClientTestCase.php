<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Operatorservice\V1\AddOrUpdateRemoteClusterRequest;
use Temporal\Api\Operatorservice\V1\AddOrUpdateRemoteClusterResponse;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\DeleteNamespaceRequest;
use Temporal\Api\Operatorservice\V1\DeleteNamespaceResponse;
use Temporal\Api\Operatorservice\V1\DeleteNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\DeleteNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\GetNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\GetNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\ListClustersRequest;
use Temporal\Api\Operatorservice\V1\ListClustersResponse;
use Temporal\Api\Operatorservice\V1\ListNexusEndpointsRequest;
use Temporal\Api\Operatorservice\V1\ListNexusEndpointsResponse;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient as ApiOperatorServiceClient;
use Temporal\Api\Operatorservice\V1\RemoveRemoteClusterRequest;
use Temporal\Api\Operatorservice\V1\RemoveRemoteClusterResponse;
use Temporal\Api\Operatorservice\V1\RemoveSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\RemoveSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\UpdateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\UpdateNexusEndpointResponse;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\GrpcClientInterface;
use Temporal\Client\GRPC\OperatorClient;
use Temporal\Client\GRPC\OperatorClientInterface;
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
            public ?DeleteNamespaceRequest $request = null;
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

                    return (new DeleteNamespaceResponse())->setDeletedNamespace('temporal-system-deleted');
                }
            }]),
        )->withAuthKey('test-key');

        $response = $client->DeleteNamespace((new DeleteNamespaceRequest())->setNamespace('test-namespace'));

        self::assertSame('DeleteNamespace', $captured->method);
        self::assertSame('test-namespace', $captured->request?->getNamespace());
        self::assertSame(['Bearer test-key'], $captured->context?->getMetadata()['Authorization'] ?? null);
        self::assertSame('temporal-system-deleted', $response->getDeletedNamespace());
    }

    #[Test]
    public function addSearchAttributes(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new AddSearchAttributesResponse(),
        );

        $client->AddSearchAttributes(new AddSearchAttributesRequest());

        self::assertSame('AddSearchAttributes', $captured->method);
        self::assertInstanceOf(AddSearchAttributesRequest::class, $captured->arg);
    }

    #[Test]
    public function removeSearchAttributes(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new RemoveSearchAttributesResponse(),
        );

        $client->RemoveSearchAttributes(new RemoveSearchAttributesRequest());

        self::assertSame('RemoveSearchAttributes', $captured->method);
        self::assertInstanceOf(RemoveSearchAttributesRequest::class, $captured->arg);
    }

    #[Test]
    public function listSearchAttributes(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new ListSearchAttributesResponse(),
        );

        $client->ListSearchAttributes(new ListSearchAttributesRequest());

        self::assertSame('ListSearchAttributes', $captured->method);
        self::assertInstanceOf(ListSearchAttributesRequest::class, $captured->arg);
    }

    #[Test]
    public function addOrUpdateRemoteCluster(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new AddOrUpdateRemoteClusterResponse(),
        );

        $client->AddOrUpdateRemoteCluster(new AddOrUpdateRemoteClusterRequest());

        self::assertSame('AddOrUpdateRemoteCluster', $captured->method);
        self::assertInstanceOf(AddOrUpdateRemoteClusterRequest::class, $captured->arg);
    }

    #[Test]
    public function removeRemoteCluster(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new RemoveRemoteClusterResponse(),
        );

        $client->RemoveRemoteCluster(new RemoveRemoteClusterRequest());

        self::assertSame('RemoveRemoteCluster', $captured->method);
        self::assertInstanceOf(RemoveRemoteClusterRequest::class, $captured->arg);
    }

    #[Test]
    public function listClusters(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new ListClustersResponse(),
        );

        $client->ListClusters(new ListClustersRequest());

        self::assertSame('ListClusters', $captured->method);
        self::assertInstanceOf(ListClustersRequest::class, $captured->arg);
    }

    #[Test]
    public function getNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new GetNexusEndpointResponse(),
        );

        $client->GetNexusEndpoint(new GetNexusEndpointRequest());

        self::assertSame('GetNexusEndpoint', $captured->method);
        self::assertInstanceOf(GetNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function createNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new CreateNexusEndpointResponse(),
        );

        $client->CreateNexusEndpoint(new CreateNexusEndpointRequest());

        self::assertSame('CreateNexusEndpoint', $captured->method);
        self::assertInstanceOf(CreateNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function updateNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new UpdateNexusEndpointResponse(),
        );

        $client->UpdateNexusEndpoint(new UpdateNexusEndpointRequest());

        self::assertSame('UpdateNexusEndpoint', $captured->method);
        self::assertInstanceOf(UpdateNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function deleteNexusEndpoint(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new DeleteNexusEndpointResponse(),
        );

        $client->DeleteNexusEndpoint(new DeleteNexusEndpointRequest());

        self::assertSame('DeleteNexusEndpoint', $captured->method);
        self::assertInstanceOf(DeleteNexusEndpointRequest::class, $captured->arg);
    }

    #[Test]
    public function listNexusEndpoints(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new ListNexusEndpointsResponse(),
        );

        $client->ListNexusEndpoints(new ListNexusEndpointsRequest());

        self::assertSame('ListNexusEndpoints', $captured->method);
        self::assertInstanceOf(ListNexusEndpointsRequest::class, $captured->arg);
    }

    #[Test]
    public function customContextIsPassedToInterceptor(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new ListClustersResponse(),
        );

        $ctx = $client->getContext()->withMetadata(['x-custom' => ['value']]);
        $client->ListClusters(new ListClustersRequest(), $ctx);

        self::assertSame(['value'], $captured->ctx->getMetadata()['x-custom'] ?? null);
    }

    #[Test]
    public function authKeyIsInjectedIntoContext(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new ListClustersResponse(),
        );

        $client = $client->withAuthKey('operator-key');
        $client->ListClusters(new ListClustersRequest());

        self::assertSame(['Bearer operator-key'], $captured->ctx->getMetadata()['Authorization'] ?? null);
    }

    #[Test]
    public function withoutAuthKeyNoAuthorizationHeader(): void
    {
        [$captured, $client] = $this->createInterceptedClient(
            static fn() => new ListClustersResponse(),
        );

        $client->ListClusters(new ListClustersRequest());

        self::assertArrayNotHasKey('Authorization', $captured->ctx->getMetadata());
    }

    #[Test]
    public function withContextReturnsNewImmutableInstance(): void
    {
        [, $client] = $this->createInterceptedClient(
            static fn() => new ListClustersResponse(),
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
            static fn() => new ListClustersResponse(),
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
            static fn() => new ListClustersResponse(),
        );

        self::assertInstanceOf(GrpcClientInterface::class, $client);
    }

    #[Test]
    public function implementsOperatorClientInterface(): void
    {
        [, $client] = $this->createInterceptedClient(
            static fn() => new ListClustersResponse(),
        );

        self::assertInstanceOf(OperatorClientInterface::class, $client);
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
