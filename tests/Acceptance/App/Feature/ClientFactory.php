<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Feature;

use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\InvokerInterface;
use Temporal\Client\ClientOptions;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\ProtoConverter;
use Temporal\DataConverter\ProtoJsonConverter;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Interceptor\PipelineProvider;

/**
 * @implements InjectorInterface<WorkflowStubInterface>
 */
#[Singleton]
final class ClientFactory
{
    public function __construct(
        #[Proxy] private readonly ContainerInterface $container,
        #[Proxy] private readonly InvokerInterface $invoker,
    ) {
    }

    public function workflowClient(\ReflectionParameter $context): WorkflowClientInterface
    {
        /** @var Client|null $attribute */
        $attribute = ($context->getAttributes(Client::class)[0] ?? null)?->newInstance();

        /** @var WorkflowClientInterface $client */
        $client = $this->container->get(WorkflowClientInterface::class);

        if ($attribute === null) {
            return $client;
        }

        if ($attribute->payloadConverters !== []) {
            $converters = [
                new NullConverter(),
                new BinaryConverter(),
                new ProtoConverter(),
                new ProtoJsonConverter(),
                new JsonConverter(),
            ];
            // Collect converters from all features
            foreach ($attribute->payloadConverters as $converterClass) {
                \array_unshift($converters, $this->container->get($converterClass));
            }
            $converter = new DataConverter(...$converters);
        } else {
            $converter = $this->container->get(DataConverterInterface::class);
        }

        /** @var PipelineProvider|null $pipelineProvider */
        $pipelineProvider = $attribute->pipelineProvider === null
            ? null
            : $this->invoker->invoke($attribute->pipelineProvider);

        // Build custom WorkflowClient with gRPC interceptor
        $serviceClient = $client->getServiceClient()
            ->withInterceptorPipeline($pipelineProvider?->getPipeline(GrpcClientInterceptor::class));

        /** @var State $runtime */
        $runtime = $this->container->get(State::class);
        $client = WorkflowClient::create(
            serviceClient: $serviceClient,
            options: (new ClientOptions())->withNamespace($runtime->namespace),
            converter: $converter,
            interceptorProvider: $pipelineProvider,
        )->withTimeout($attribute->timeout ?? 5);

        return $client;
    }
}
