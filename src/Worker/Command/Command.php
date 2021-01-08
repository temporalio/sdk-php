<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Command;

abstract class Command implements CommandInterface
{
    /**
     * @var int
     */
    protected static int $lastID = 9000;

    /**
     * @var int
     */
    protected int $id;

    /**
     * @param int|null $id
     */
    public function __construct(int $id = null)
    {
        $this->id = $id ?? $this->getNextID();
    }

    /**
     * @return int
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    private function getNextID(): int
    {
        $next = ++static::$lastID;

        if ($next >= \PHP_INT_MAX) {
            $next = static::$lastID = 1;
        }

        return $next;
    }
}
