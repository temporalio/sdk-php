<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

/**
 * Support for PHP7.4
 * @Temporal\Activity\ActivityInterface(prefix="DummyActivity")
 */
#[ActivityInterface(prefix: 'DummyActivity')]
final class DummyActivity
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
