<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Testing;

use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Worker\LoopInterface;

final class TestingLoop implements LoopInterface
{
    use EventEmitterTrait;

    /**
     * @return void
     */
    public function tick(): void
    {
        $this->emit(LoopInterface::ON_TICK);
    }

    /**
     * @return int
     */
    public function run(): int
    {
        $this->tick();

        return 0;
    }
}
