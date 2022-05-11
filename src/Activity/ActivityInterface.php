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
use JetBrains\PhpStorm\Immutable;
use Spiral\Attributes\NamedArgumentConstructorAttribute;

/**
 * Indicates that an interface is an activity interface. Only interfaces
 * annotated with this annotation can be used as parameters
 * to {@see Workflow::activity()}
 * and {@see WorkflowContextInterface::newActivityStub()} methods.
 *
 * Each method of the interface annotated with {@see ActivityInterface}
 * including inherited from interfaces is a separate activity. By default the
 * name of an activity type is its method name with the first letter
 * capitalized. Use {@see ActivityInterface::$prefix}
 * or {@see ActivityMethod::$name} to make sure that activity type names are
 * distinct.
 *
 * @Annotation
 * @Target({ "CLASS" })
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ActivityInterface implements NamedArgumentConstructorAttribute
{
    /**
     * Prefix to prepend to method names to generate activity types. Default is
     * empty string which means that method names are used as activity types.
     *
     * Note that this value is ignored if a name of an activity is specified
     * explicitly through {@see ActivityMethod::$name}.
     *
     * Be careful about names that contain special characters. These names can
     * be used as metric tags. And systems like prometheus ignore metrics which
     * have tags with unsupported characters.
     */
    #[Immutable]
    public string $prefix = '';

    /**
     * @param string $prefix
     */
    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }
}
