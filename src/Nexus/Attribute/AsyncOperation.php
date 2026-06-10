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
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\WorkflowHandle;

/**
 * Marks a method on a {@see Service}-annotated type (interface or class) as an asynchronous
 * Nexus operation. The method returns a {@see WorkflowHandle} (SDK-managed workflow run) or,
 * for full manual control, an {@see OperationHandlerInterface} implementation.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class AsyncOperation
{
    /**
     * @param string $name Operation name as exposed over the wire. Empty means "use the method name".
     * @param string $output Wire output type the async operation eventually produces. Empty means "void".
     * @param string $input Wire input type; used only for {@see OperationHandlerInterface} factories,
     *        whose zero-parameter signature cannot carry it. Empty means "mixed".
     */
    public function __construct(
        public readonly string $name = '',
        public readonly string $output = '',
        public readonly string $input = '',
    ) {}
}
