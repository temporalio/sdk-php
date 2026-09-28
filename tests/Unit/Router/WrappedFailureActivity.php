<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;

#[ActivityInterface(prefix: 'WrappedFailureActivity.')]
final class WrappedFailureActivity
{
    #[ActivityMethod(name: 'Throw')]
    public function throw(): void
    {
        throw new \RuntimeException(
            'wrapper',
            0,
            new ApplicationFailure('inner', 'Inner', true, EncodedValues::fromValues(['detail'])),
        );
    }
}
