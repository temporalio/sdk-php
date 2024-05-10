<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Common\BackoffThrottler;

final class BackoffThrottlerTestCase extends TestCase
{
    public static function provideConstructorInvalidArguments(): iterable
    {
        yield 'maxInterval is 0' => [0, 0.1, 2.0, '$maxInterval must be greater than 0.'];
        yield 'maxJitterCoefficient is negative' => [1, -0.1, 2.0, '$jitterCoefficient must be in the range [0.0, 1.0).'];
        yield 'maxJitterCoefficient is 1' => [1, 1.0, 2.0, '$jitterCoefficient must be in the range [0.0, 1.0).'];
        yield 'backoffCoefficient is less than 1' => [1, 0.1, 0.9, '$backoffCoefficient must be greater than 1.'];
    }

    #[DataProvider('provideConstructorInvalidArguments')]
    public function testInvalidArguments(
        int $maxInterval,
        float $maxJitterCoefficient,
        float $backoffCoefficient,
        ?string $exceptionMessage = null,
    ): void {
        $exceptionMessage === null or $this->expectExceptionMessage($exceptionMessage);

        new BackoffThrottler($maxInterval, $maxJitterCoefficient, $backoffCoefficient);
    }

    public static function provideCalculatorData(): iterable
    {
        yield 'first attempt' => [1000, 1, 1000];
        yield 'second attempt' => [1500, 2, 500];
        yield 'third attempt' => [4500, 3, 500];
        yield 'overflow' => [300_000, 100, 500];
    }

    #[DataProvider('provideCalculatorData')]
    public function testCalculateSleepTime(int $expected, int $fails, int $interval): void
    {
        $throttler = new BackoffThrottler(300_000, 0.0, 3.0);

        self::assertSame($expected, $throttler->calculateSleepTime($fails, $interval));
    }

    public static function provideCalculatorInvalidArgs(): iterable
    {
        yield 'fails is negative' => [-1, 100];
        yield 'fails is zero' => [0, 100];
        yield 'interval is negative' => [1, -100];
        yield 'interval is zero' => [1, 0];
    }

    #[DataProvider('provideCalculatorInvalidArgs')]
    public function testCalculateSleepTimeInvalidArgs(int $fails, int $interval): void
    {
        $throttler = new BackoffThrottler(300_000, 0.0, 3.0);

        $this->expectException(\InvalidArgumentException::class);
        $throttler->calculateSleepTime($fails, $interval);
    }

    public function testCalculateSleepTimeWithJitter(): void
    {
        $throttler = new BackoffThrottler(300_000, 0.2, 2.0);

        $sleepTime = $throttler->calculateSleepTime(1, 1000);
        $notSame = false;

        for ($i = 20; --$i;) {
            $sleep = $throttler->calculateSleepTime(1, 1000);
            $notSame = $notSame || $sleep !== $sleepTime;

            self::assertGreaterThanOrEqual(800, $sleep);
            self::assertLessThanOrEqual(1200, $sleep);
        }

        self::assertTrue($notSame);
    }
}
