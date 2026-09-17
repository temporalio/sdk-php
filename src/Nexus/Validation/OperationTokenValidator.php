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
 * Operation token — printable non-whitespace ASCII (Nexus spec).
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
