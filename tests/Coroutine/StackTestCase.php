<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Coroutine;

use Temporal\Internal\Coroutine\AppendableInterface;
use Temporal\Internal\Coroutine\Stack;

class StackTestCase extends CoroutineTestCase
{
    public function testStackCallable(): void
    {
        $first = $second = false;

        $stack = $this->create([3, 4, 5], function () use (&$first) { $first = true; });
        $stack->push([1, 2], function () use (&$second) { $second = true; });

        $this->assertSame([1, 2, 3, 4, 5], \iterator_to_array($stack, false));
        $this->assertTrue($first, 'Root generator should be completed');
        $this->assertTrue($second, 'Child generator should be completed');
    }

    public function testEmptyStackCallable(): void
    {
        $first = $second = false;

        $stack = $this->create([], function () use (&$first) { $first = true; });
        $stack->push([], function () use (&$second) { $second = true; });

        $this->assertSame([], \iterator_to_array($stack, false));
        $this->assertTrue($first, 'Root generator should be completed');
        $this->assertTrue($second, 'Child generator should be completed');
    }

    /**
     * @param iterable|array $values
     * @param \Closure|null $then
     * @return AppendableInterface
     */
    private function create(iterable $values = [], \Closure $then = null): AppendableInterface
    {
        return new Stack($values, $then);
    }
}
