<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\Http;

final class Client
{
    public const NO_TIMEOUT = 0;
    private const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * @param int $timeoutMs {@see self::NO_TIMEOUT} waits for as long as the server holds the connection
     */
    public function get(string $url, int $timeoutMs = self::NO_TIMEOUT): Response
    {
        $headers = [];
        $handle = $this->handle($url, $timeoutMs);
        \curl_setopt($handle, \CURLOPT_HEADERFUNCTION, static function (\CurlHandle $handle, string $line) use (&$headers): int {
            $parts = \explode(':', $line, 2);
            if (\count($parts) === 2) {
                $headers[\strtolower(\trim($parts[0]))] = \trim($parts[1]);
            }

            return \strlen($line);
        });

        return $this->send($handle, $headers);
    }

    public function postJson(string $url, string $body, int $timeoutMs): Response
    {
        $handle = $this->handle($url, $timeoutMs);
        \curl_setopt($handle, \CURLOPT_POST, true);
        \curl_setopt($handle, \CURLOPT_POSTFIELDS, $body);
        \curl_setopt($handle, \CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $headers = [];

        return $this->send($handle, $headers);
    }

    private function handle(string $url, int $timeoutMs): \CurlHandle
    {
        $handle = \curl_init($url);
        if ($handle === false) {
            throw new TransportException('Unable to initialise a curl handle');
        }

        \curl_setopt($handle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($handle, \CURLOPT_FAILONERROR, false);
        \curl_setopt($handle, \CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        \curl_setopt($handle, \CURLOPT_TIMEOUT_MS, $timeoutMs);

        return $handle;
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(\CurlHandle $handle, array &$headers): Response
    {
        $body = \curl_exec($handle);
        if ($body === false) {
            throw new TransportException(\curl_error($handle));
        }

        return new Response(
            status: (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE),
            body: (string) $body,
            headers: $headers,
        );
    }
}
