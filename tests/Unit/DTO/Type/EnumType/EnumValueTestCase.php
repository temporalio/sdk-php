<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\EnumType;

use Temporal\Internal\Marshaller\Type\EnumValueType as EnumType;
use Temporal\Tests\Unit\DTO\AbstractDTOMarshalling;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\EnumValueDto as EnumDto;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\ScalarEnum;

class EnumValueTestCase extends AbstractDTOMarshalling
{
    public function testMarshal(): void
    {
        $dto = new EnumDto();
        $dto->scalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->autoScalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->nullable = null;

        $result = $this->marshal($dto);
        $this->assertSame('tested', $result['scalarEnum']);
        $this->assertSame('tested', $result['autoScalarEnum']);
        $this->assertNull($result['nullable']);
    }

    public function testMarshalEnumIntoNullable(): void
    {
        $dto = new EnumDto();
        $dto->nullable = ScalarEnum::TESTED_ENUM;

        $result = $this->marshal($dto);
        $this->assertSame('tested', $result['nullable']);
    }

    public function testUnmarshalBackedEnumUsingScalarValue(): void
    {
        $dto = $this->unmarshal([
            'scalarEnum' => ScalarEnum::TESTED_ENUM->value,
        ], new EnumDto());

        $this->assertSame(ScalarEnum::TESTED_ENUM, $dto->scalarEnum);
    }

    public function testUnmarshalEnumUsingNameInArray(): void
    {
        $this->expectException(\Temporal\Exception\MarshallerException::class);

        $this->unmarshal([
            'scalarEnum' => ['name' => ScalarEnum::TESTED_ENUM->name],
        ], new EnumDto());
    }

    public function testMarshalAndUnmarshalSame(): void
    {
        $dto = new EnumDTO();
        $dto->scalarEnum = ScalarEnum::TESTED_ENUM;
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
                'autoScalarEnum' => null,
            ], new EnumDto());

            $this->fail('Null value should not be allowed.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString(
                '`autoScalarEnum`',
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
