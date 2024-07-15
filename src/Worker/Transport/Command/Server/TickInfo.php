<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Server;

final class TickInfo
{
    /**
     * @param int<0, max> $historyLength
     * @param int<0, max> $historySize
     */
    public function __construct(
        public readonly \DateTimeInterface $time,
        public readonly int $historyLength = 0,
        public readonly int $historySize = 0,
        public readonly bool $continueAsNewSuggested = false,
        public readonly bool $isReplaying = false,
    ) {}
}
