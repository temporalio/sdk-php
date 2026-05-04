<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Attribute;

use Doctrine\Common\Annotations\Annotation\Target;
use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Marks a method on a {@see Service}-annotated type (interface or class) as a Nexus operation.
 *
 * The method signature defines the operation's input and output types. By default, the
 * operation name is the method name; override it via {@see self::$name}.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class Operation
{
    /**
     * @param string $name Operation name as exposed over the wire. Empty means "use the method name".
     */
    public function __construct(
        public readonly string $name = '',
    ) {}
}
