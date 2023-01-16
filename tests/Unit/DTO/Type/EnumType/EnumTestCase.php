<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\EnumType;

use Temporal\Internal\Marshaller\Type\EnumType;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;
use Temporal\Tests\Unit\DTO\Type\EnumType\EnumType\EnumDTO;
use Temporal\Tests\Unit\DTO\Type\EnumType\EnumType\ScalarEnum;
use Temporal\Tests\Unit\DTO\Type\EnumType\EnumType\SimpleEnum;

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

        $result = $this->marshal($dto);
        $this->assertEquals($dto->simpleEnum, $result['simpleEnum']);
        $this->assertEquals($dto->scalarEnum, $result['scalarEnum']);
        $this->assertEquals($dto->autoSimpleEnum, $result['autoSimpleEnum']);
        $this->assertEquals($dto->autoScalarEnum, $result['autoScalarEnum']);
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
        $this->expectError();

        $this->unmarshal([
            'simpleEnum' => SimpleEnum::TEST->name,
        ], new EnumDTO());
    }

    protected function getTypeMatchers(): array
    {
        return [
            static fn (\ReflectionNamedType $type): ?string => EnumType::match($type) ? EnumType::class : null,
        ];
    }
}
