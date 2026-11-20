<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Bridge;

use PHPUnit\Framework\TestCase;
use Temporal\Internal\Bridge\BridgeConnection;

final class BridgeConnectionTestCase extends TestCase
{
    private const PEM = "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----\n";

    public function testClientUsesHttpsOnlyWithTls(): void
    {
        self::assertSame(
            ['target_url' => 'http://host:7233', 'tls' => null, 'connect_timeout_ms' => BridgeConnection::CONNECT_TIMEOUT_MS],
            BridgeConnection::client('host:7233', null),
        );
        self::assertSame('https://host:7233', BridgeConnection::client('host:7233', [])['target_url']);
    }

    public function testTlsReadsCertificateFiles(): void
    {
        $file = (string) \tempnam(\sys_get_temp_dir(), 'pem');
        \file_put_contents($file, self::PEM);
        try {
            self::assertSame(
                ['server_root_ca_cert' => self::PEM, 'domain' => 'example.com', 'client_cert' => self::PEM, 'client_private_key' => null],
                BridgeConnection::tls($file, 'example.com', self::PEM, ''),
            );
        } finally {
            \unlink($file);
        }
    }
}
