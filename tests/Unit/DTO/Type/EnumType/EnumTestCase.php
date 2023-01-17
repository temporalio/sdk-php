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
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\EnumDTO;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\ScalarEnum;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\SimpleEnum;

/**
 * @requires PHP >= 8.1
 */
class EnumTestCase extends DTOMarshallingTestCase
{
    public function testMarshal(): void
    {
        $dto = new EnumDTO();
        $dto->simpleEnum = SimpleEnum::TEST;
        $dto->scalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->autoSimpleEnum = SimpleEnum::TEST;
        $dto->autoScalarEnum = ScalarEnum::TESTED_ENUM;
        $dto->nullable = null;

        $result = $this->marshal($dto);
        $this->assertEquals($dto->simpleEnum, $result['simpleEnum']);
        $this->assertEquals($dto->scalarEnum, $result['scalarEnum']);
        $this->assertEquals($dto->autoSimpleEnum, $result['autoSimpleEnum']);
        $this->assertEquals($dto->autoScalarEnum, $result['autoScalarEnum']);
        $this->assertNull($result['nullable']);
    }

    public function testMarshalEnumIntoNullable(): void
    {
        $dto = new EnumDTO();
        $dto->nullable = ScalarEnum::TESTED_ENUM;

        $result = $this->marshal($dto);
        $this->assertEquals(ScalarEnum::TESTED_ENUM, $result['nullable']);
    }

    public function testUnmarshalBackedEnum(): void
    {
        $dto = $this->unmarshal([
            'scalarEnum' => ScalarEnum::TESTED_ENUM->value,
        ], new EnumDTO());

        $this->assertSame(ScalarEnum::TESTED_ENUM, $dto->scalarEnum);
    }

    public function testUnmarshalNonBackedEnum(): void
    {
        try {
            $this->unmarshal([
                'simpleEnum' => SimpleEnum::TEST->name,
            ], new EnumDTO());

            $this->fail('Expected exception');
        }catch (\Throwable $e) {
            $this->assertInstanceOf(Error::class, $e->getPrevious());
        }
    }

    protected function getTypeMatchers(): array
    {
        return [
            EnumType::class,
        ];
    }

    // public function testMarshalAndUnmarshalSame(): void
    // {
    //     $dto = new EnumDTO();
    //     $dto->simpleEnum = SimpleEnum::TEST;
    //     $dto->scalarEnum = ScalarEnum::TESTED_ENUM;
    //     $dto->autoSimpleEnum = SimpleEnum::TEST;
    //     $dto->autoScalarEnum = ScalarEnum::TESTED_ENUM;
    //     $dto->nullable = null;
    //
    //     $result = $this->marshal($dto);
    //     $unmarshal = $this->unmarshal($result, new EnumDTO());
    //
    //     $this->assertEquals($dto, $unmarshal);
    // }
}
