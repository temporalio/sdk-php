<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow\Process;


/**
 * @internal CancellationScope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
class CancellationScope extends Scope
{
    /**
     * @param mixed $result
     */
    protected function onComplete($result): void
    {
        //
    }
}
