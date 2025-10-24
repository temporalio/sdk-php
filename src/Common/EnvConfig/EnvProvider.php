<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

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
}
