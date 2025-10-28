<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common\EnvConfig;

/**
 * System environment variable provider that reads from {@see $_ENV} and {@see getenv()}.
 *
 * This implementation prioritizes {@see $_ENV} over {@see getenv()} for better performance
 * and consistency with PHP's environment variable handling.
 */
final class SystemEnvProvider implements EnvProvider
{
    public function get(string $name, ?string $default = null): ?string
    {
        // Try $_ENV first for better performance
        if (isset($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        // Fallback to getenv() for environment variables not in $_ENV
        $value = \getenv($name);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    public function getByPrefix(string $prefix, bool $stripPrefix = false): array
    {
        $result = [];
        $prefixLength = \strlen($prefix);

        // Search in $_ENV
        foreach ($_ENV as $key => $value) {
            if (\str_starts_with($key, $prefix)) {
                $resultKey = $stripPrefix ? \substr($key, $prefixLength) : $key;
                if ($resultKey !== '') {
                    $result[$resultKey] = (string) $value;
                }
            }
        }

        // Search in getenv() for variables not in $_ENV
        // Note: getenv() without arguments returns all environment variables
        $envVars = \getenv();
        foreach ($envVars as $key => $value) {
            if (\str_starts_with($key, $prefix) && !isset($_ENV[$key])) {
                $resultKey = $stripPrefix ? \substr($key, $prefixLength) : $key;
                if ($resultKey !== '' && !\array_key_exists($resultKey, $result)) {
                    $result[$resultKey] = $value;
                }
            }
        }

        return $result;
    }
}
