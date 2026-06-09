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
 * @internal Shared check for printable non-whitespace ASCII (0x21–0x7E).
 */
final class PrintableAsciiValidator
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @throws InvalidArgumentException when $value is empty or contains a byte
     *         outside the printable ASCII range 0x21–0x7E.
     */
    public static function assert(string $value, string $label): void
    {
        if ($value === '') {
            throw new InvalidArgumentException("{$label} must not be empty");
        }

        if (\preg_match('/[^\x21-\x7E]/', $value, $matches, \PREG_OFFSET_CAPTURE) === 1) {
            throw new InvalidArgumentException(\sprintf(
                '%s must contain only printable non-whitespace ASCII (0x21–0x7E); got %d bytes, first bad char at offset %d',
                $label,
                \strlen($value),
                $matches[0][1],
            ));
        }
    }
}
