<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Temporal\Activity\ActivityMethod;
use Temporal\Activity\LocalActivityInterface;

/**
 * Support for PHP7.4
 * @Temporal\Activity\LocalActivityInterface(prefix="DummyLocalActivity")
 */
#[LocalActivityInterface(prefix: 'DummyLocalActivity')]
final class DummyLocalActivity
{
    /**
     * Support for PHP7.4
     * @Temporal\Activity\ActivityMethod(name="DoNothing")
     */
    #[ActivityMethod(name: "DoNothing")]
    public function doNothing(): void
    {
    }
}
