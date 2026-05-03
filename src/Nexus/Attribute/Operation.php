<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Attribute;

/**
 * Marks a method on a {@see Service}-annotated interface as a Nexus operation.
 *
 * The method signature defines the operation's input and output types. By default, the
 * operation name is the method name; override it via {@see self::$name}.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Operation
{
    /**
     * @param string $name Operation name as exposed over the wire. Empty means "use the method name".
     */
    public function __construct(
        public readonly string $name = '',
    ) {}
}
