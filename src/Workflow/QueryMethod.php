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
use JetBrains\PhpStorm\Immutable;
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
     * Name of the query type. Default is method name.
     *
     * Be careful about names that contain special characters. These names can
     * be used as metric tags. And systems like prometheus ignore metrics which
     * have tags with unsupported characters.
     *
     * @var string|null
     */
    #[Immutable]
    public ?string $name = null;

    /**
     * @param string|null $name
     */
    public function __construct(string $name = null)
    {
        $this->name = $name;
    }
}
