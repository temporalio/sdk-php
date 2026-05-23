<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\CronType;

#[CoversClass(CronType::class)]
final class CronTypeTestCase extends TestCase
{
    private CronType $type;

    protected function setUp(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $this->type = new CronType($marshaller);
    }

    public function testParseEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->type->parse('', null));
    }

    public function testParseValidCronString(): void
    {
        $this->assertSame('*/5 * * * *', $this->type->parse('*/5 * * * *', null));
    }

    public function testParseNonStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cron-like string');

        $this->type->parse(42, null);
    }

    public function testSerializeString(): void
    {
        $this->assertSame('*/5 * * * *', $this->type->serialize('*/5 * * * *'));
    }

    public function testSerializeStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return '0 0 * * *';
            }
        };

        $this->assertSame('0 0 * * *', $this->type->serialize($stringable));
    }

    public function testSerializeInvalidTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cron-like string');

        $this->type->serialize(42);
    }
}
