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
 * Indicates that the method is an update handler method.
 * An update method gets executed when a workflow receives an update after the validator is called.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class UpdateMethod
{
    /**
     * @param non-empty-string|null $name Name of the update handler. Default is method name.
     *        Be careful about names that contain special characters. These names can be used as metric tags.
     *        And systems like prometheus ignore metrics which have tags with unsupported characters.
     *        Name cannot start with `__temporal` as it is reserved for internal use.
     * @param HandlerUnfinishedPolicy $unfinishedPolicy Actions taken if a workflow exits with
     *        a running instance of this handler.
     * @param string $description Short description of the update handler.
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly HandlerUnfinishedPolicy $unfinishedPolicy = HandlerUnfinishedPolicy::WarnAndAbandon,
        public readonly string $description = '',
    ) {}
}
