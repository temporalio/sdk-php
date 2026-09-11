<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller;

use Google\Protobuf\Any;
use Google\Protobuf\BoolValue;
use Google\Protobuf\Struct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

/**
 * @internal
 */
#[CoversClass(ProtoToArrayConverter::class)]
final class ProtoToArrayConverterMapperTestCase extends TestCase
{
    public static function messagesWithoutMapper(): iterable
    {
        yield 'struct' => [new Struct()];
        yield 'bool value' => [new BoolValue()];
        yield 'any' => [new Any()];
    }

    #[DataProvider('messagesWithoutMapper')]
    public function testGoogleMessageWithoutMapperIsPassedThrough(object $message): void
    {
        $converter = new ProtoToArrayConverter(DataConverter::createDefault());

        $this->assertSame($message, $converter->convert($message));
    }
}
