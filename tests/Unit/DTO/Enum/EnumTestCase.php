<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Enum;

use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;

class EnumTestCase extends DTOMarshallingTestCase
{
    public function testMarshalling(): void
    {
        if (PHP_VERSION_ID < 80104) {
            $this->markTestSkipped();
        }

        $dto = new EnumDTO();
        $dto->simpleEnum = SimpleEnum::TEST;
        $dto->scalarEnum = ScalarEnum::TESTED_ENUM;

        $result = $this->marshal($dto);
        $this->assertEquals($dto->simpleEnum, $result['simpleEnum']);
        $this->assertEquals($dto->scalarEnum, $result['scalarEnum']);
    }
}
