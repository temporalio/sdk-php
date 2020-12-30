<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Activity;

class SimpleActivity
{
    #[\Temporal\Activity\ActivityMethod]
    public function echo($value)
    {
        \sleep(\random_int(1, 3));
        return $value;
    }

    #[\Temporal\Activity\ActivityMethod]
    public function asyncActivity(): int
    {
        Activity::doNotCompleteOnReturn();

        return 0xDEAD_BEEF;
    }
}
