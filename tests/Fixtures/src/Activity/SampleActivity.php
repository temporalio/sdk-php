<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Activity;

class SampleActivity implements SampleActivityInterface
{
    public function multiply(int $value): int
    {
        return $value * 10;
    }

    public function store(int $value): void
    {
        // doing nothing
    }
}
