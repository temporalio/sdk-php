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
 * The annotated method must be public and non-static and return `void`. It may declare,
 * in any order, any of the following parameters; arguments are matched by type, not by
 * position:
 *
 * - no parameters;
 * - a single `string` parameter, which receives the operation token (legacy signature);
 * - a {@see \Temporal\Nexus\Handler\OperationContext} parameter, which receives the
 *   operation context;
 * - a {@see \Temporal\Nexus\Handler\OperationCancelDetails} parameter, which receives the
 *   cancel details (including the operation token).
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
