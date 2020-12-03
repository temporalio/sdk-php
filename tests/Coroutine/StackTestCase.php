<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Coroutine;

use Temporal\Client\Internal\Coroutine\Stack;

class StackTestCase extends CoroutineTestCase
{
    /**
     * @return void
     */
    public function testStackInjectable(): void
    {
        $stack = new Stack([5, 6, 7]);
        $stack->push([1, 4]);

        $this->assertSame($stack->current(), 1);

        $stack->next();
        $stack->push([2, 3]);

        foreach (\range(2, 7) as $i) {
            $this->assertSame($i, $stack->current());
            $stack->next();
        }
    }
}
