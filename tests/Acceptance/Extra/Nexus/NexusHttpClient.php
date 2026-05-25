<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NexusHttpClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * POST a Nexus operation. Returns [http_code, body, headers-lowercased-keys].
     *
     * The Temporal frontend's HTTP endpoint cache is lazy: a freshly-created
     * endpoint can return 404 for a few ms after CreateNexusEndpoint succeeds.
     * Mirrors Go SDK's nexus_test helper (10 attempts × 100ms).
     *
     * @param mixed $body
     * @param array<string, string> $headers
     * @return array{int, string, array<string, list<string>>}
     */
    public function post(
        NexusEndpoint $endpoint,
        string $service,
        string $operation,
        mixed $body,
        array $headers = [],
    ): array {
        $attempts = 10;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $response = $this->http->request(
                'POST',
                "/nexus/endpoints/{$endpoint->id}/services/{$service}/{$operation}",
                [
                    'headers' => $headers,
                    'json' => $body,
                    'max_duration' => 30,
                ],
            );
            $code = $response->getStatusCode();
            if ($code !== 404 || $attempt === $attempts) {
                return [$code, $response->getContent(false), $response->getHeaders(false)];
            }
            \usleep(100_000);
        }
        throw new \LogicException('Unreachable');
    }
}
