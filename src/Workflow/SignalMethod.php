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
 * Indicates that the method is a signal handler method. Signal method is
 * executed when workflow receives signal. This annotation applies only to
 * workflow interface methods.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class SignalMethod
{

    /**
     * @param non-empty-string|null $name Signal name.
     * @param HandlerUnfinishedPolicy $unfinishedPolicy Actions taken if a workflow exits with
     *         a running instance of this handler.
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly HandlerUnfinishedPolicy $unfinishedPolicy = HandlerUnfinishedPolicy::WarnAndAbandon,
    ) {}
}
