<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Validation;

use Temporal\Nexus\Exception\InvalidArgumentException;

/**
 * @internal Shared check for the `__temporal_` prefix reserved by the Temporal server.
 */
final class ReservedPrefixValidator
{
    public const PREFIX = '__temporal_';

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @throws InvalidArgumentException when $value starts with {@see self::PREFIX}.
     *
     * @psalm-mutation-free
     */
    public static function assert(string $value, string $label): void
    {
        if (\str_starts_with($value, self::PREFIX)) {
            throw new InvalidArgumentException(\sprintf(
                '%s "%s" must not start with "%s"',
                $label,
                $value,
                self::PREFIX,
            ));
        }
    }
}
