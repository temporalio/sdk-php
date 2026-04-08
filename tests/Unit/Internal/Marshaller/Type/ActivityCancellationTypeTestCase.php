<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Activity\ActivityCancellationType as Policy;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\ActivityCancellationType;

#[CoversClass(ActivityCancellationType::class)]
final class ActivityCancellationTypeTestCase extends TestCase
{
    private ActivityCancellationType $type;

    protected function setUp(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $this->type = new ActivityCancellationType($marshaller);
    }

    public function testParseTrue(): void
    {
        $this->assertSame(Policy::WAIT_CANCELLATION_COMPLETED, $this->type->parse(true, null));
    }

    public function testParseFalse(): void
    {
        $this->assertSame(Policy::TRY_CANCEL, $this->type->parse(false, null));
    }

    public function testSerializeWaitCancellationCompleted(): void
    {
        $this->assertTrue($this->type->serialize(Policy::WAIT_CANCELLATION_COMPLETED));
    }

    public function testSerializeTryCancel(): void
    {
        $this->assertFalse($this->type->serialize(Policy::TRY_CANCEL));
    }

    public function testSerializeUnsupportedOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is currently not supported');

        $this->type->serialize(Policy::ABANDON);
    }
}
