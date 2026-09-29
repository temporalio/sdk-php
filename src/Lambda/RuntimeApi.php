<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

final class RuntimeApi
{
    public const RESPONSE_RESERVE_MS = 1_000;
    private const VERSION = '2018-06-01';
    private const CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(private readonly string $host) {}

    public function nextInvocation(): Invocation
    {
        $headers = [];
        $handle = $this->handle($this->url('runtime/invocation/next'));
        \curl_setopt($handle, \CURLOPT_TIMEOUT, 0);
        \curl_setopt($handle, \CURLOPT_HEADERFUNCTION, static function (\CurlHandle $handle, string $line) use (&$headers): int {
            $parts = \explode(':', $line, 2);
            if (\count($parts) === 2) {
                $headers[\strtolower(\trim($parts[0]))] = \trim($parts[1]);
            }

            return \strlen($line);
        });

        $this->send($handle, 'fetch the next invocation');

        $requestId = $headers['lambda-runtime-aws-request-id'] ?? '';
        if ($requestId === '') {
            throw new \RuntimeException('Runtime API response is missing Lambda-Runtime-Aws-Request-Id');
        }

        $deadlineMs = (int) ($headers['lambda-runtime-deadline-ms'] ?? 0);
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
        $handle = $this->handle($this->url($path));
        \curl_setopt($handle, \CURLOPT_POST, true);
        \curl_setopt($handle, \CURLOPT_POSTFIELDS, $body);
        \curl_setopt($handle, \CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        \curl_setopt($handle, \CURLOPT_TIMEOUT_MS, self::RESPONSE_RESERVE_MS);

        $this->send($handle, $what);
    }

    private function handle(string $url): \CurlHandle
    {
        $handle = \curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialise a curl handle');
        }

        \curl_setopt($handle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($handle, \CURLOPT_FAILONERROR, false);
        \curl_setopt($handle, \CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);

        return $handle;
    }

    private function send(\CurlHandle $handle, string $what): void
    {
        $body = \curl_exec($handle);
        $error = \curl_error($handle);
        $status = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);

        if ($body === false) {
            throw new \RuntimeException("Failed to {$what}: {$error}");
        }

        if ($status < 200 || $status > 299) {
            throw new \RuntimeException("Failed to {$what}: HTTP {$status}");
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
