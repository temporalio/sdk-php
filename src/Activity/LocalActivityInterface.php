<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Doctrine\Common\Annotations\Annotation\Target;
use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Indicates that an interface is a local activity interface.
 *
 * Only interfaces annotated with this annotation can be used as parameters to {@see Workflow::activity()}
 * and {@see WorkflowContextInterface::newActivityStub()} methods.
 *
 * Each method of the interface annotated with {@see LocalActivityInterface}
 * including inherited from interfaces is a separate activity. By default, the
 * name of an activity type is its method name with the first letter
 * capitalized. Use {@see LocalActivityInterface::$prefix}
 * or {@see ActivityMethod::$name} to make sure that activity type names are
 * distinct.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "CLASS" })
 */
#[\Attribute(\Attribute::TARGET_CLASS), NamedArgumentConstructor]
final class LocalActivityInterface extends ActivityInterface {}
