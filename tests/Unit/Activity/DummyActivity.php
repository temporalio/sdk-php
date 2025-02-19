<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DummyActivity')]
final class DummyActivity
{
    #[ActivityMethod(name: "DoNothing")]
    public function doNothing(): void {}
}
