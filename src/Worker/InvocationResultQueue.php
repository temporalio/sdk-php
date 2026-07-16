<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

final class InvocationResultQueue
{
    /**
     * @param list<InvocationResult> $items
     */
    public function __construct(
        private array $items,
    ) {
        if ($this->items === []) {
            throw new \InvalidArgumentException('Consecutive completions require at least one result.');
        }
    }

    public function current(): InvocationResult
    {
        $current = \reset($this->items);
        \assert($current !== false);

        return $current;
    }

    public function advance(): void
    {
        if (\count($this->items) > 1) {
            \array_shift($this->items);
        }
    }
}
