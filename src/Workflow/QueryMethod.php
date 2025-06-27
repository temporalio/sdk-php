<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Doctrine\Common\Annotations\Annotation\Target;
use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Indicates that the method is a query method. Query method can be used to
 * query a workflow state by external process at any time during its execution.
 * This annotation/attribute applies only to workflow interface methods.
 *
 * Query methods must never change any workflow state including starting
 * activities or block threads in any way.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class QueryMethod
{
    /**
     * @param non-empty-string|null $name Query method name. Default is method name.
     *        Name cannot start with `__temporal` as it is reserved for internal use. The name also cannot
     *        be `__stack_trace` or `__enhanced_stack_trace` as they are reserved for internal use.
     * @param string $description Short description of the query type.
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly string $description = '',
    ) {}
}
