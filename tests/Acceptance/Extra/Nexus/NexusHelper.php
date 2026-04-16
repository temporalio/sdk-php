<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

use Symfony\Component\Process\Process;

/**
 * Shared helpers for Nexus acceptance tests.
 */
final class NexusHelper
{
    public const HTTP_PORT = 7243;

    /**
     * Create a Nexus endpoint pointing to a worker task queue.
     */
    public static function createEndpoint(string $name, string $namespace, string $taskQueue, string $address): bool
    {
        $temporal = \getenv('TEMPORAL_CLI') ?: './temporal';

        $process = new Process([
            $temporal,
            'operator', 'nexus', 'endpoint', 'create',
            '--name', $name,
            '--target-namespace', $namespace,
            '--target-task-queue', $taskQueue,
            '--address', $address,
        ]);
        $process->setTimeout(10);

        try {
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolve endpoint UUID by its name.
     */
    public static function getEndpointId(string $name, string $address): ?string
    {
        $temporal = \getenv('TEMPORAL_CLI') ?: './temporal';

        $process = new Process([
            $temporal,
            'operator', 'nexus', 'endpoint', 'list',
            '--address', $address,
            '--output', 'json',
        ]);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        try {
            $data = \json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!\is_array($data)) {
            return null;
        }

        foreach ($data as $entry) {
            $epName = $entry['endpoint']['spec']['name'] ?? $entry['spec']['name'] ?? null;
            $epId = $entry['endpoint']['id'] ?? $entry['id'] ?? null;
            if ($epName === $name && \is_string($epId)) {
                return $epId;
            }
        }

        return null;
    }

    /**
     * Send a Nexus HTTP POST request.
     *
     * @param string $host Temporal server host
     * @param string $endpointId Nexus endpoint UUID
     * @param string $service Service name
     * @param string $operation Operation name
     * @param mixed $body Body to JSON-encode
     * @param array<string, string> $extraHeaders Extra HTTP headers
     * @return array{int, string|false} [http_code, response_body]
     */
    public static function postNexus(
        string $host,
        string $endpointId,
        string $service,
        string $operation,
        mixed $body,
        array $extraHeaders = [],
    ): array {
        $url = "http://{$host}:" . self::HTTP_PORT . "/nexus/endpoints/{$endpointId}/services/{$service}/{$operation}";

        $headers = ['Content-Type: application/json'];
        foreach ($extraHeaders as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = \curl_exec($ch);
        $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        return [$httpCode, $response];
    }

    /**
     * Generate a unique endpoint name (Nexus name regex: [a-zA-Z][a-zA-Z0-9-]*[a-zA-Z0-9]).
     */
    public static function uniqueEndpointName(string $prefix = 'test-nexus'): string
    {
        return $prefix . '-' . \bin2hex(\random_bytes(4));
    }
}
