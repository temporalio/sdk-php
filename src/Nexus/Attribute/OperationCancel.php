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
 * Marks a method on a service implementation class as the cancel routine for a named
 * {@see AsyncOperation}.
 *
 * The annotated method must be public and non-static. It receives the operation token
 * as its sole argument and returns `void`.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class OperationCancel
{
    /**
     * @param string $operation Name of the async operation this cancel routine targets,
     *     matching either the {@see AsyncOperation::$name} on the contract method or — if
     *     left empty there — the contract method's own name.
     */
    public function __construct(
        public readonly string $operation,
    ) {}
}
