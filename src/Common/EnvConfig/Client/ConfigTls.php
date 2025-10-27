<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig\Client;

use Temporal\Client\GRPC\BaseClient;

/**
 * gRPC TLS configuration.
 *
 * Arguments for {@see BaseClient::createSSL()}.
 *
 * @internal
 */
final class ConfigTls
{
    /**
     * @param bool $disabled Whether to disable TLS.
     * @param non-empty-string|null $rootCerts Root certificates string or file in PEM format.
     *        If null provided, default gRPC root certificates are used.
     * @param non-empty-string|null $privateKey Client private key string or file in PEM format.
     * @param non-empty-string|null $certChain Client certificate chain string or file in PEM format.
     * @param non-empty-string|null $serverName Server name override for TLS verification.
     */
    public function __construct(
        public readonly ?bool $disabled = false,
        public readonly ?string $rootCerts = null,
        public readonly ?string $privateKey = null,
        public readonly ?string $certChain = null,
        public readonly ?string $serverName = null,
    ) {}

    public function mergeWith(ConfigTls $from): self
    {
        return new self(
            disabled: $from->disabled ?? $this->disabled,
            rootCerts: $from->rootCerts ?? $this->rootCerts,
            privateKey: $from->privateKey ?? $this->privateKey,
            certChain: $from->certChain ?? $this->certChain,
            serverName: $from->serverName ?? $this->serverName,
        );
    }
}
