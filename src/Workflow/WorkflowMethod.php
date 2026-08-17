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
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class WorkflowMethod
{
    /**
     * Name of the workflow type. Default is "{class_name :: method_name}".
     *
     * Be careful about names that contain special characters. These names can
     * be used as metric tags. And systems like prometheus ignore metrics which
     * have tags with unsupported characters.
     *
     * @var non-empty-string|null
     */
    #[Immutable]
    public ?string $name = null;

    /**
     * Marks this as a dynamic (catch-all) workflow: it is invoked when the
     * worker receives a workflow whose type name is not statically registered.
     * At most one dynamic workflow may be registered per worker. The handler
     * reads the actual type name via {@see \Temporal\Workflow::getInfo()} and
     * receives the raw arguments (declare a {@see \Temporal\DataConverter\ValuesInterface}
     * parameter to access them).
     */
    #[Immutable]
    public bool $dynamic = false;

    /**
     * @param non-empty-string|null $name
     */
    public function __construct(?string $name = null, bool $dynamic = false)
    {
        $this->name = $name;
        $this->dynamic = $dynamic;
    }
}
