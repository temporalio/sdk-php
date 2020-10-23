<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use React\Promise\PromiseInterface;

use function React\Promise\all;
use function React\Promise\any;
use function React\Promise\map;
use function React\Promise\reduce;
use function React\Promise\some;

/**
 * @mixin PromiseAwareInterface
 */
trait PromiseAwareTrait
{
    /**
     * {@inheritDoc}
     */
    public function all(iterable $promises): PromiseInterface
    {
        return all($this->iterableToArray($promises));
    }

    /**
     * {@inheritDoc}
     */
    public function any(iterable $promises): PromiseInterface
    {
        return any($this->iterableToArray($promises));
    }

    /**
     * {@inheritDoc}
     */
    public function some(iterable $promises, int $count): PromiseInterface
    {
        return some($this->iterableToArray($promises), $count);
    }

    /**
     * {@inheritDoc}
     */
    public function map(iterable $promises, callable $map): PromiseInterface
    {
        return map($this->iterableToArray($promises), $map);
    }

    /**
     * {@inheritDoc}
     */
    public function reduce(iterable $promises, callable $reduce, $initial = null): PromiseInterface
    {
        return reduce($this->iterableToArray($promises), $reduce, $initial);
    }

    /**
     * @param array|\Traversable|iterable $items
     * @return array
     */
    private function iterableToArray(iterable $items): array
    {
        return $items instanceof \Traversable ? \iterator_to_array($items, false) : $items;
    }
}
