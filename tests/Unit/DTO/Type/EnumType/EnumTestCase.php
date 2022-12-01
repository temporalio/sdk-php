<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\EnumType;

use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;

/**
 * @requires PHP >= 8.1
 */
class EnumTestCase extends DTOMarshallingTestCase
{
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
}
