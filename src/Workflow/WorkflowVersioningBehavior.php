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
use Temporal\Common\VersioningBehavior;

/**
 * Indicates the versioning behavior of the Workflow.
 * May only be applied to workflow implementations, not interfaces.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 *
 * @see \Temporal\Api\Enums\V1\VersioningBehavior
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class WorkflowVersioningBehavior
{
    public function __construct(
        /**
         * The behavior to apply to this workflow.
         *
         * See {@link VersioningBehavior} for more information.
         */
        public readonly VersioningBehavior $value,
    ) {}
}
