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
 * Indicates that the method is an update validator handle. An update validator handle is associated
 * with an update method and runs before the associated update handle. If the update validator
 * throws an exception, the update handle is not called and the update is not persisted in history.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class UpdateValidatorMethod
{
    /**
     * @param non-empty-string $forUpdate Name of the update handler the validator should be used for.
     *        Be careful about names that contain special characters. These names can be used as metric tags.
     *        And systems like prometheus ignore metrics which have tags with unsupported characters.
     */
    public function __construct(
        #[Immutable]
        public string $forUpdate,
    ) {
    }
}
