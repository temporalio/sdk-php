<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\Basic;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface]
class FeatureActivity
{
    #[ActivityMethod('echo')]
    public function echo(): string
    {
        return 'echo';
    }
}
