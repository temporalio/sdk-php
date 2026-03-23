<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temporal\Activity;
use Temporal\Exception\OutOfContextException;

class ActivityFacadeTest extends TestCase
{
    /**
     * @return iterable<string, array{callable}>
     */
    public static function outOfContextMethods(): iterable
    {
        yield 'getCurrentContext' => [
            static fn() => Activity::getCurrentContext(),
        ];

        yield 'getInfo' => [
            static fn() => Activity::getInfo(),
        ];

        yield 'getInput' => [
            static fn() => Activity::getInput(),
        ];

        yield 'hasHeartbeatDetails' => [
            static fn() => Activity::hasHeartbeatDetails(),
        ];

        yield 'getHeartbeatDetails' => [
            static fn() => Activity::getHeartbeatDetails(),
        ];

        yield 'getCancellationDetails' => [
            static fn() => Activity::getCancellationDetails(),
        ];

        yield 'doNotCompleteOnReturn' => [
            static fn() => Activity::doNotCompleteOnReturn(),
        ];

        yield 'heartbeat' => [
            static fn() => Activity::heartbeat('test'),
        ];

        yield 'getInstance' => [
            static fn() => Activity::getInstance(),
        ];
    }

    #[Test]
    #[DataProvider('outOfContextMethods')]
    public function throwsOutOfContextException(callable $method): void
    {
        $this->expectException(OutOfContextException::class);
        $this->expectExceptionMessage('The Activity facade can only be used in the context of an activity execution.');

        $method();
    }
}
