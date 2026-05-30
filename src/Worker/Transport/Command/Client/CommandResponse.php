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

final class CommandResponse implements ResponseInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $command,
        private readonly array $options = [],
        private readonly ?ValuesInterface $payloads = null,
        private readonly ?\Throwable $failure = null,
    ) {}

    public function getID(): int
    {
        return 0;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getPayloads(): ?ValuesInterface
    {
        return $this->payloads;
    }

    public function getFailure(): ?\Throwable
    {
        return $this->failure;
    }
}
