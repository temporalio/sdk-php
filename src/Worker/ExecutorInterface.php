<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

interface ExecutorInterface
{
    /**
     * @var string
     */
    public const DEFAULT_WORKER_ID = 'default';

    /**
     * @param string $name
     * @return int
     */
    public function run(string $name = self::DEFAULT_WORKER_ID): int;
}
