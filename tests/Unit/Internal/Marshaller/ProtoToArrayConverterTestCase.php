<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller;

use Google\Protobuf\Duration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;
use Temporal\Internal\Support\DateInterval;

#[CoversClass(ProtoToArrayConverter::class)]
final class ProtoToArrayConverterTestCase extends TestCase
{
    public function testDurationPreservesExactSeconds(): void
    {
        $converter = new ProtoToArrayConverter(DataConverter::createDefault());

        $interval = $converter->convert(
            (new Duration())->setSeconds(365 * 24 * 60 * 60),
        );

        self::assertInstanceOf(\DateInterval::class, $interval);
        self::assertSame(
            365 * 24 * 60 * 60,
            (int) DateInterval::parse($interval)->totalSeconds,
        );
    }
}
