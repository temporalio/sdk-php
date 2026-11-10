<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Temporal\Exception\DataConverterException;

/**
 * @internal
 */
final class StorageBudget
{
    private int $inFlight = 0;

    public function __construct(
        private readonly int $limit,
    ) {}

    public function acquire(): void
    {
        if ($this->inFlight >= $this->limit) {
            throw new DataConverterException(\sprintf(
                'The external storage budget of %d operations is exhausted: a storage driver takes a permit inside another permit.',
                $this->limit,
            ));
        }

        ++$this->inFlight;
    }

    public function release(): void
    {
        --$this->inFlight;
    }
}
