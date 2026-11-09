<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use React\Promise\PromiseInterface;

/**
 * A deterministic suspension instruction emitted only by {@see Awaiter}.
 *
 * @internal
 * @psalm-internal Temporal
 */
final class FiberSuspension
{
    public function __construct(
        public readonly PromiseInterface $promise,
        public readonly bool $interruptOnCancel,
    ) {}
}
