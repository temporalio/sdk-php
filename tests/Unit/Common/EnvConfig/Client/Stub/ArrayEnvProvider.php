<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common\EnvConfig\Client\Stub;

use Temporal\Common\EnvConfig\EnvProvider;

/**
 * Test implementation of EnvProvider using an array as data source.
 *
 * Useful for testing and providing controlled environment variable values.
 */
final class ArrayEnvProvider implements EnvProvider
{
    /**
     * @param array<non-empty-string, string> $variables
     */
    public function __construct(
        private array $variables = [],
    ) {}

    public function get(string $name, ?string $default = null): ?string
    {
        return $this->variables[$name] ?? $default;
    }

    public function getByPrefix(string $prefix, bool $stripPrefix = false): array
    {
        $result = [];
        foreach ($this->variables as $key => $value) {
            if (\str_starts_with($key, $prefix)) {
                $resultKey = $stripPrefix ? \substr($key, \strlen($prefix)) : $key;
                if ($resultKey !== '') {
                    $result[$resultKey] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Set environment variable for testing.
     *
     * @param non-empty-string $name
     */
    public function set(string $name, string $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * Unset environment variable for testing.
     *
     * @param non-empty-string $name
     */
    public function unset(string $name): void
    {
        unset($this->variables[$name]);
    }

    /**
     * Clear all environment variables.
     */
    public function clear(): void
    {
        $this->variables = [];
    }
}