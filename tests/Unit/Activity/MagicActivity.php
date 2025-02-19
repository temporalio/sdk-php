<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'MagicActivity.')]
final class MagicActivity
{
    public function __construct() {}

    #[ActivityMethod(name: "Do")]
    public function __invoke(): void {}

    public function __destruct() {}
}
