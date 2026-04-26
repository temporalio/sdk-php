<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Temporal\Api\Nexus\V1\EndpointSpec;
use Temporal\Api\Nexus\V1\EndpointTarget;
use Temporal\Api\Nexus\V1\EndpointTarget\Worker as WorkerTarget;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient;
use Temporal\Tests\Acceptance\App\Runtime\State;

/**
 * Shared helpers for Nexus acceptance tests.
 *
 * Endpoint management goes over gRPC OperatorService (no shelling out to `temporal` CLI).
 * Nexus operation invocation goes over Symfony HttpClient (no raw curl).
 */
final class NexusHelper
{
    public const HTTP_PORT = 7243;

    public function __construct(
        private readonly OperatorServiceClient $operator,
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Build a helper bound to the running test environment.
     */
    public static function for(State $state): self
    {
        $host = \parse_url("http://{$state->address}", PHP_URL_HOST) ?: '127.0.0.1';

        return new self(
            new OperatorServiceClient(
                $state->address,
                ['credentials' => \Grpc\ChannelCredentials::createInsecure()],
            ),
            HttpClient::createForBaseUri("http://{$host}:" . self::HTTP_PORT),
        );
    }

    /**
     * Create a Nexus endpoint targeting the given worker task queue and return its server-assigned ID.
     */
    public function createWorkerEndpoint(string $name, string $namespace, string $taskQueue): string
    {
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

        return $response->getEndpoint()->getId();
    }

    /**
     * Convenience: create a worker endpoint with a unique generated name and return its ID.
     */
    public function setupEndpoint(string $namespace, string $taskQueue, string $prefix = 'test-nexus'): string
    {
        return $this->createWorkerEndpoint(self::uniqueEndpointName($prefix), $namespace, $taskQueue);
    }

    /**
     * Same as {@see self::setupEndpoint()} but returns both the server-assigned id
     * (used by HTTP routes) and the human-readable name (used by SDK callers via
     * {@see \Temporal\Workflow\NexusOperationOptions::withEndpoint()}).
     *
     * @return array{id: string, name: string}
     */
    public function setupEndpointWithName(string $namespace, string $taskQueue, string $prefix = 'test-nexus'): array
    {
        $name = self::uniqueEndpointName($prefix);
        return [
            'id' => $this->createWorkerEndpoint($name, $namespace, $taskQueue),
            'name' => $name,
        ];
    }

    /**
     * Send a Nexus HTTP POST request to a registered endpoint.
     *
     * @param mixed $body Body to JSON-encode
     * @param array<string, string> $extraHeaders Extra HTTP headers
     * @return array{int, string} [http_code, response_body]
     */
    public function postOperation(
        string $endpointId,
        string $service,
        string $operation,
        mixed $body,
        array $extraHeaders = [],
    ): array {
        [$code, $respBody, ] = $this->postOperationFull($endpointId, $service, $operation, $body, $extraHeaders);
        return [$code, $respBody];
    }

    /**
     * Same as {@see self::postOperation()} but also returns response headers.
     *
     * Use when asserting on wire-level details like `Nexus-Link` or
     * `Nexus-Operation-State` that don't appear in the body.
     *
     * @param mixed $body
     * @param array<string, string> $extraHeaders
     * @return array{int, string, array<string, list<string>>} [http_code, body, headers-lowercased-keys]
     */
    public function postOperationFull(
        string $endpointId,
        string $service,
        string $operation,
        mixed $body,
        array $extraHeaders = [],
    ): array {
        $response = $this->http->request(
            'POST',
            "/nexus/endpoints/{$endpointId}/services/{$service}/{$operation}",
            [
                'headers' => $extraHeaders,
                'json' => $body,
                // Total request budget: 30s. `timeout` (idle) defaults to 60s — server-side
                // Nexus polls can stall for several seconds while the worker picks the task up.
                'max_duration' => 30,
            ],
        );

        return [$response->getStatusCode(), $response->getContent(false), $response->getHeaders(false)];
    }

    /**
     * Generate a unique endpoint name (Nexus name regex: [a-zA-Z][a-zA-Z0-9-]*[a-zA-Z0-9]).
     */
    public static function uniqueEndpointName(string $prefix = 'test-nexus'): string
    {
        return $prefix . '-' . \bin2hex(\random_bytes(4));
    }
}
