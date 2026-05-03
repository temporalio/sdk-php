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
 * Validates a Service Name per the Nexus spec.
 *
 * Travels in the request URL path and the `Nexus-Service` header, so only
 * printable non-whitespace ASCII (0x21–0x7E) is accepted.
 */
final class ServiceNameValidator
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    /**
     * @throws InvalidArgumentException
     */
    public static function assert(string $name): void
    {
        PrintableAsciiValidator::assert($name, 'Service Name');
    }
}
