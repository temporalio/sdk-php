<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

abstract class Response implements ResponseInterface
{
    /**
     * @param int<0, max> $historyLength
     */
    public function __construct(
        private readonly string|int $id,
        private readonly int $historyLength,
    ) {
    }

    public function getID(): string|int
    {
        return $this->id;
    }

    public function getHistoryLength(): int
    {
        return $this->historyLength;
    }
}
