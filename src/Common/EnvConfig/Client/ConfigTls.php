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
    /** @var non-empty-string|null Root certificates string or file in PEM format */
    public readonly ?string $rootCerts;

    /** @var non-empty-string|null Client private key string or file in PEM format */
    public readonly ?string $privateKey;

    /** @var non-empty-string|null Client certificate chain string or file in PEM format */
    public readonly ?string $certChain;

    /** @var non-empty-string|null Server name override for TLS verification */
    public readonly ?string $serverName;

    /**
     * @param bool $disabled Whether to disable TLS.
     * @param string|null $rootCerts Root certificates string or file in PEM format.
     *        If null provided, default gRPC root certificates are used.
     * @param string|null $privateKey Client private key string or file in PEM format.
     * @param string|null $certChain Client certificate chain string or file in PEM format.
     * @param string|null $serverName Server name override for TLS verification.
     */
    public function __construct(
        public readonly ?bool $disabled = false,
        ?string $rootCerts = null,
        ?string $privateKey = null,
        ?string $certChain = null,
        ?string $serverName = null,
    ) {
        $this->rootCerts = $rootCerts === '' ? null : $rootCerts;
        $this->privateKey = $privateKey === '' ? null : $privateKey;
        $this->certChain = $certChain === '' ? null : $certChain;
        $this->serverName = $serverName === '' ? null : $serverName;
    }

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
