<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

/**
 * gRPC TLS configuration.
 * @internal
 */
final class ConfigTls
{
    /**
     * @param non-empty-string|null $rootCerts Root certificates string or file in PEM format.
     *        If null provided, default gRPC root certificates are used.
     * @param non-empty-string|null $privateKey Client private key string or file in PEM format.
     * @param non-empty-string|null $certChain Client certificate chain string or file in PEM format.
     * @param non-empty-string|null $serverName Server name override for TLS verification.
     */
    public function __construct(
        public readonly ?string $rootCerts = null,
        public readonly ?string $privateKey = null,
        public readonly ?string $certChain = null,
        public readonly ?string $serverName = null,
    ) {}
}
