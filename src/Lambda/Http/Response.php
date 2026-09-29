<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\Http;

final class Response
{
    /**
     * @param array<string, string> $headers Header names are lower-cased
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status <= 299;
    }

    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }
}
