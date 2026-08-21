<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Internal\Headers;

/**
 * Case-insensitive string map. Keys are normalized to lowercase (last wins).
 */
final class HeaderCollection
{
    /** @var array<string, string> Lowercased keys. */
    private array $headers;

    /**
     * @param array<string, string> $initial
     */
    public function __construct(array $initial = [])
    {
        $this->headers = Headers::normalize($initial);
    }

    public function get(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    public function has(string $name): bool
    {
        return \array_key_exists(\strtolower($name), $this->headers);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->headers;
    }
}
