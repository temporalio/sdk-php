<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Internal;

/**
 * @internal
 */
final class Headers
{
    /**
     * Lowercase keys; last value wins on collision.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public static function normalize(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[\strtolower($key)] = $value;
        }
        return $normalized;
    }

    /** @codeCoverageIgnore */
    private function __construct() {}
}
