<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

use Temporal\Lambda\Http\Client;
use Temporal\Lambda\Http\Response;

final class RuntimeApi
{
    public const RESPONSE_RESERVE_MS = 1_000;
    private const VERSION = '2018-06-01';

    private readonly Client $client;

    public function __construct(
        private readonly string $host,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    public function nextInvocation(): Invocation
    {
        $response = $this->client->get($this->url('runtime/invocation/next'), Client::NO_TIMEOUT);
        $this->assertSuccessful($response, 'fetch the next invocation');

        $requestId = $response->header('Lambda-Runtime-Aws-Request-Id') ?? '';
        if ($requestId === '') {
            throw new \RuntimeException('Runtime API response is missing Lambda-Runtime-Aws-Request-Id');
        }

        $deadlineMs = (int) ($response->header('Lambda-Runtime-Deadline-Ms') ?? 0);
        if ($deadlineMs <= 0) {
            throw new \RuntimeException('Runtime API response is missing Lambda-Runtime-Deadline-Ms');
        }

        return new Invocation($requestId, $deadlineMs);
    }

    public function respond(string $requestId): void
    {
        $this->post("runtime/invocation/{$requestId}/response", 'null', 'send the invocation response');
    }

    public function reportInvocationError(string $requestId, \Throwable $error): void
    {
        $this->post(
            "runtime/invocation/{$requestId}/error",
            $this->encodeError($error),
            'report the invocation error',
        );
    }

    public function reportInitError(\Throwable $error): void
    {
        $this->post('runtime/init/error', $this->encodeError($error), 'report the init error');
    }

    private function post(string $path, string $body, string $what): void
    {
        $response = $this->client->postJson($this->url($path), $body, self::RESPONSE_RESERVE_MS);

        $this->assertSuccessful($response, $what);
    }

    private function assertSuccessful(Response $response, string $what): void
    {
        if (!$response->isSuccessful()) {
            throw new \RuntimeException(
                \rtrim("Failed to {$what}: HTTP {$response->status} {$response->body}"),
            );
        }
    }

    private function url(string $path): string
    {
        return "http://{$this->host}/" . self::VERSION . "/{$path}";
    }

    private function encodeError(\Throwable $error): string
    {
        return (string) \json_encode([
            'errorType' => \str_replace('\\', '.', $error::class),
            'errorMessage' => $error->getMessage(),
            'stackTrace' => \explode("\n", $error->getTraceAsString()),
        ]);
    }
}
