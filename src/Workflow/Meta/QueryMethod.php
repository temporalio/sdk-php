<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Meta;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Indicates that the method is a query method. Query method can be used to
 * query a workflow state by external process at any time during its execution.
 * This annotation/attribute applies only to workflow interface methods.
 *
 * Query methods must never change any workflow state including starting
 * activities or block threads in any way.
 *
 * @Annotation
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
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
    public ?string $name = null;
}
