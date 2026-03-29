<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Api\Operatorservice\V1\DeleteNamespaceRequest;
use Temporal\Api\Operatorservice\V1\DeleteNamespaceResponse;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient as ApiOperatorServiceClient;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\OperatorClient;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Tests\TestCase;

#[CoversClass(\Temporal\Client\GRPC\OperatorClient::class)]
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
}
