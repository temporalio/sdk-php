<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Carbon\CarbonInterval;
use PHPUnit\Framework\TestCase;
use Temporal\Workflow\AwaitOptions;
use Temporal\Workflow\TimerOptions;

final class AwaitOptionsTestCase extends TestCase
{
    public function testIntervalIsParsedFromSeconds(): void
    {
        $options = AwaitOptions::new(42);

        self::assertSame(42.0, CarbonInterval::make($options->interval)?->totalSeconds);
        self::assertNull($options->options);
    }

    public function testIntervalAcceptsDateInterval(): void
    {
        $options = AwaitOptions::new(CarbonInterval::minutes(3));

        self::assertSame(180.0, CarbonInterval::make($options->interval)?->totalSeconds);
    }

    public function testWithTimerOptionsIsImmutable(): void
    {
        $options = AwaitOptions::new(5);
        $timerOptions = TimerOptions::new()->withSummary('test summary');

        $result = $options->withTimerOptions($timerOptions);

        self::assertNotSame($options, $result);
        self::assertNull($options->options);
        self::assertSame($timerOptions, $result->options);
        self::assertSame($options->interval, $result->interval);
    }

    public function testWithIntervalIsImmutable(): void
    {
        $timerOptions = TimerOptions::new()->withSummary('test summary');
        $options = AwaitOptions::new(5, $timerOptions);

        $result = $options->withInterval(10);

        self::assertNotSame($options, $result);
        self::assertSame(5.0, CarbonInterval::make($options->interval)?->totalSeconds);
        self::assertSame(10.0, CarbonInterval::make($result->interval)?->totalSeconds);
        self::assertSame($timerOptions, $result->options);
    }
}
