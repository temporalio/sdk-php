<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Activity;

#[\Temporal\Activity\ActivityInterface]
interface SampleActivityInterface
{
    public function multiply(int $value): int;

    public function store(int $value): void;
}
