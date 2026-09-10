<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\DataConverter\Type;

#[ActivityInterface(prefix: 'HeartbeatDetailsActivity.')]
final class HeartbeatDetailsActivity
{
    #[ActivityMethod(name: 'ReadSignature')]
    public function readSignature(): string
    {
        return Activity::getHeartbeatDetails(Type::TYPE_STRING);
    }
}
