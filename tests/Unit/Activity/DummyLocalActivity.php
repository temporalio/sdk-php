<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Temporal\Activity\ActivityMethod;
use Temporal\Activity\LocalActivityInterface;

#[LocalActivityInterface(prefix: 'DummyLocalActivity')]
final class DummyLocalActivity
{
    #[ActivityMethod(name: "DoNothing")]
    public function doNothing(): void
    {
    }
}
