<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\TestCase;
use Temporal\Worker\Core\RunState;

final class RunStateTestCase extends TestCase
{
    public function testReleasedSequenceIsUnknown(): void
    {
        $run = new RunState('run');
        $seq = $run->bind(10, RunState::TIMER);

        self::assertSame(10, $run->release($seq));
        $this->expectExceptionObject(new \OutOfBoundsException("Unknown command sequence $seq in run run"));
        $run->requestId($seq);
    }
}
