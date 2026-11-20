<?php

declare(strict_types=1);

namespace Grpc;

use Temporal\Internal\Bridge\BridgeConnection;

class ChannelCredentials
{
    private static ?string $defaultRootsPem = null;

    private function __construct(
        private readonly ?string $rootCerts,
        private readonly ?string $privateKey,
        private readonly ?string $certChain,
    ) {}

    public static function setDefaultRootsPem(string $pem_roots): void
    {
        self::$defaultRootsPem = $pem_roots;
    }

    public static function isDefaultRootsPemSet(): bool
    {
        return self::$defaultRootsPem !== null;
    }

    public static function createSsl(?string $pem_root_certs = null, ?string $pem_private_key = null, ?string $pem_cert_chain = null): self
    {
        return new self($pem_root_certs, $pem_private_key, $pem_cert_chain);
    }

    public static function createInsecure(): null
    {
        return null;
    }

    /**
     * @internal
     * @return array<string, ?string>
     */
    public function tls(?string $serverName): array
    {
        return BridgeConnection::tls($this->rootCerts ?? self::$defaultRootsPem, $serverName, $this->certChain, $this->privateKey);
    }
}
