<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\EnumType;

use Error;
use Temporal\Internal\Marshaller\Type\EnumType;
use Temporal\Tests\Unit\DTO\AbstractDTOMarshalling;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\EnumDto;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\ScalarEnum;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\SimpleEnum;

class EnumTestCase extends AbstractDTOMarshalling
{
    public function testMarshal(): void
    {
        $dto = new EnumDto();
        $dto->simpleEnum = SimpleEnum::TEST;
        $dto->scalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->autoSimpleEnum = SimpleEnum::TEST;
        $dto->autoScalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->nullable = null;

        $result = $this->marshal($dto);
        $this->assertSame(['name' => 'TEST'], $result['simpleEnum']);
        $this->assertSame(['name' => 'TESTED_ENUM', 'value' => 'tested'], $result['scalarEnum']);
        $this->assertSame(['name' => 'TEST'], $result['autoSimpleEnum']);
        $this->assertSame(['name' => 'TESTED_ENUM', 'value' => 'tested'], $result['autoScalarEnum']);
        $this->assertNull($result['nullable']);
    }

    public function testMarshalEnumIntoNullable(): void
    {
        $dto = new EnumDto();
        $dto->nullable = ScalarEnum::TESTED_ENUM;

        $result = $this->marshal($dto);
        $this->assertSame(['name' => 'TESTED_ENUM', 'value' => 'tested'], $result['nullable']);
    }

    public function testUnmarshalBackedEnumUsingScalarValue(): void
    {
        $dto = $this->unmarshal([
            'scalarEnum' => ScalarEnum::TESTED_ENUM->value,
        ], new EnumDto());

        $this->assertSame(ScalarEnum::TESTED_ENUM, $dto->scalarEnum);
    }

    public function testUnmarshalBackedEnumUsingValueInArray(): void
    {
        $dto = $this->unmarshal([
            'scalarEnum' => ['value' => ScalarEnum::TESTED_ENUM->value],
        ], new EnumDto());

        $this->assertSame(ScalarEnum::TESTED_ENUM, $dto->scalarEnum);
    }

    public function testUnmarshalEnumUsingNameInArray(): void
    {
        $dto = $this->unmarshal([
            'simpleEnum' => ['name' => SimpleEnum::TEST->name],
        ], new EnumDto());

        $this->assertSame(SimpleEnum::TEST, $dto->simpleEnum);
    }

    public function testUnmarshalNonBackedEnumUsingScalarArgument(): void
    {
        try {
            $this->unmarshal([
                'simpleEnum' => SimpleEnum::TEST->name,
            ], new EnumDto());

            $this->fail('Expected exception');
        }catch (\Throwable $e) {
            $this->assertInstanceOf(Error::class, $e->getPrevious());
        }
    }

    public function testMarshalAndUnmarshalSame(): void
    {
        $dto = new EnumDTO();
        $dto->simpleEnum = SimpleEnum::TEST;
        $dto->scalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->autoSimpleEnum = SimpleEnum::TEST;
        $dto->autoScalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->nullable = null;

        $result = $this->marshal($dto);
        $unmarshal = $this->unmarshal($result, new EnumDTO());

        $this->assertEquals($dto, $unmarshal);
    }

    public function testUnmarshalNullToNotNullable(): void
    {
        try {
            $this->unmarshal([
                'autoSimpleEnum' => null,
            ], new EnumDto());

            $this->fail('Null value should not be allowed.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString(
                '`autoSimpleEnum`',
                $e->getMessage(),
            );
            $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            $this->assertStringContainsString(
                'Invalid Enum value',
                $e->getPrevious()->getMessage(),
            );
        }
    }

    protected function getTypeMatchers(): array
    {
        return [
            EnumType::class,
        ];
    }
}
