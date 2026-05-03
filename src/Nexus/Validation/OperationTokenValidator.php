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
 * Validates an Operation Token per the Nexus spec.
 *
 * Opaque identifier carried in the `Nexus-Operation-Token` header, so only
 * printable non-whitespace ASCII (0x21–0x7E) is accepted.
 */
final class OperationTokenValidator
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @throws InvalidArgumentException
     */
    public static function assert(string $token): void
    {
        PrintableAsciiValidator::assert($token, 'Operation Token');
    }
}
