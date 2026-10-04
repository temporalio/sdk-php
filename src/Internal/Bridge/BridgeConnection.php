<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Bridge;

/**
 * @internal
 */
final class BridgeConnection
{
    public const CONNECT_TIMEOUT_MS = 10_000;

    /**
     * @param array<string, ?string>|null $tls
     * @return array{target_url: string, tls: array<string, ?string>|null, connect_timeout_ms: int}
     */
    public static function client(string $address, ?array $tls): array
    {
        return [
            'target_url' => ($tls === null ? 'http://' : 'https://') . $address,
            'tls' => $tls,
            'connect_timeout_ms' => self::CONNECT_TIMEOUT_MS,
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public static function tls(?string $rootCerts, ?string $serverName, ?string $certChain, ?string $privateKey): array
    {
        return [
            'server_root_ca_cert' => self::certificate($rootCerts),
            'domain' => $serverName,
            'client_cert' => self::certificate($certChain),
            'client_private_key' => self::certificate($privateKey),
        ];
    }

    public static function certificate(?string $fileOrPem): ?string
    {
        if ($fileOrPem === null || $fileOrPem === '') {
            return null;
        }
        if (!\is_file($fileOrPem)) {
            return $fileOrPem;
        }
        $content = \file_get_contents($fileOrPem);
        if ($content === false) {
            throw new \InvalidArgumentException("Failed to load certificate from file `$fileOrPem`.");
        }

        return $content;
    }
}
