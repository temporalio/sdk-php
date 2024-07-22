<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\RetryOnError;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Exception\Failure\ApplicationFailure;

#[ActivityInterface]
class FeatureActivity
{
    #[ActivityMethod('always_fail_activity')]
    public function alwaysFailActivity(): string
    {
        $attempt = Activity::getInfo()->attempt;
        throw new ApplicationFailure(
            message: "activity attempt {$attempt} failed",
            type: "CustomError",
            nonRetryable: false,
        );
    }
}
