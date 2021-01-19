<?php


namespace Temporal\Tests\Activity;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: "HeartBeatActivity.")]
class HeartBeatActivity
{
    #[ActivityMethod]
    public function doSomething(int $value): string
    {
        Activity::heartbeat(['value' => $value]);
        sleep($value);
        return 'OK';
    }

    #[ActivityMethod]
    public function something(string $value): string
    {
        Activity::heartbeat(['value' => $value]);
        sleep($value);
        return 'OK';
    }

    #[ActivityMethod]
    public function slow(
        string $value
    ): string {
        for ($i = 0; $i < 5; $i++) {
            // todo: try cancel
            Activity::heartbeat(['value' => $i]);
            sleep(1);
        }

        return 'OK';
    }
}
