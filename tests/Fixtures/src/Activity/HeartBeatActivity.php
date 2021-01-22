<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Activity;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: "HeartBeatActivity.")]
class HeartBeatActivity
{
    #[ActivityMethod]
    public function doSomething(
        int $value
    ): string {
        Activity::heartbeat(['value' => $value]);
        sleep($value);
        return 'OK';
    }

    #[ActivityMethod]
    public function something(
        string $value
    ): string {
        Activity::heartbeat(['value' => $value]);
        sleep($value);
        return 'OK';
    }

    #[ActivityMethod]
    public function slow(
        string $value
    ): string {
        for ($i = 0; $i < 10; $i++) {
            Activity::heartbeat(['value' => $i]);
            sleep(1);
        }

        return 'OK';
    }
}
