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
 * Interface for environment variable provider
 */
interface EnvProvider
{
    /**
     * Get environment variable by name
     *
     * @param non-empty-string $name
     * @param string|null $default
     * @return string|null
     */
    public function get(string $name, ?string $default = null): ?string;

    /**
     * Get all environment variables with the given prefix
     *
     * @param non-empty-string $prefix Prefix to filter environment variables
     * @param bool $stripPrefix Whether to remove the prefix from keys in the result.
     *        Note: Keys that become empty strings after prefix removal will be excluded from results.
     * @return array<non-empty-string, string>
     */
    public function getByPrefix(string $prefix, bool $stripPrefix = false): array;
}
