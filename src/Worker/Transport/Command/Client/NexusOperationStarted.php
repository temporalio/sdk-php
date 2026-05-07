<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Client;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;

/**
 * Typed handler-side reply to `InvokeNexusOperation`. Replaces the prior
 * `_rr_nexus_kind` / `_rr_nexus_links` payload-metadata markers — the Go
 * plugin decodes this command into `internal.NexusOperationStarted{Async,
 * Token, Links}`.
 *
 * @internal
 */
final class NexusOperationStarted implements ResponseInterface
{
    public const COMMAND = 'NexusOperationStarted';

    /**
     * @param list<array{url: string, type: string}> $links
     */
    public function __construct(
        private readonly bool $async,
        private readonly ?string $token = null,
        private readonly array $links = [],
        private readonly ?ValuesInterface $payloads = null,
    ) {}

    public function getID(): int
    {
        return 0;
    }

    public function getCommand(): string
    {
        return self::COMMAND;
    }

    /**
     * @return array{async: bool, token?: string, links?: list<array{url: string, type: string}>}
     */
    public function getOptions(): array
    {
        $options = ['async' => $this->async];
        if ($this->token !== null) {
            $options['token'] = $this->token;
        }
        if ($this->links !== []) {
            $options['links'] = $this->links;
        }
        return $options;
    }

    public function getPayloads(): ?ValuesInterface
    {
        return $this->payloads;
    }

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @return list<array{url: string, type: string}>
     */
    public function getLinks(): array
    {
        return $this->links;
    }
}
