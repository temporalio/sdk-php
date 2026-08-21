<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

use Temporal\Api\Nexus\V1\EndpointSpec;
use Temporal\Api\Nexus\V1\EndpointTarget;
use Temporal\Api\Nexus\V1\EndpointTarget\Worker as WorkerTarget;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient;

final class NexusEndpoints
{
    public function __construct(
        private readonly OperatorServiceClient $operator,
    ) {}

    public function register(
        string $namespace,
        string $taskQueue,
        string $prefix = 'test-nexus',
    ): NexusEndpoint {
        $name = $prefix . '-' . \bin2hex(\random_bytes(4));

        $request = (new CreateNexusEndpointRequest())
            ->setSpec(
                (new EndpointSpec())
                    ->setName($name)
                    ->setTarget(
                        (new EndpointTarget())->setWorker(
                            (new WorkerTarget())
                                ->setNamespace($namespace)
                                ->setTaskQueue($taskQueue),
                        ),
                    ),
            );

        [$response, $status] = $this->operator->CreateNexusEndpoint($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException(
                "CreateNexusEndpoint failed (gRPC code {$status->code}): {$status->details}",
            );
        }

        return new NexusEndpoint(id: $response->getEndpoint()->getId(), name: $name);
    }
}
