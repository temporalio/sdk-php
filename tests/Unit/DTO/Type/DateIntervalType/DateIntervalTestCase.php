<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\DateIntervalType;

use DateInterval;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;
use Temporal\Tests\Unit\DTO\Type\DateIntervalType\Stub\DateIntervalDto;

class DateIntervalTestCase extends DTOMarshallingTestCase
{
    public function testMarshalAndUnmarshalWithoutAttribute(): void
    {
        $dto = new DateIntervalDto();
        $dto->interval = new \DateInterval('P1Y2M3DT4H5M6S');

        $result = $this->marshal($dto);
        $unmarshal = $this->unmarshal($result, new DateIntervalDto());

        self::assertInstanceOf(DateInterval::class, $unmarshal->interval);
    }

    // public function testMarshalAndUnmarshalCorrectValue(): void
    // {
    //     $dto = new DateIntervalDto();
    //     $dto->interval = new \DateInterval('P1Y2M3DT4H5M6S');
    //
    //     $result = $this->marshal($dto);
    //     $unmarshal = $this->unmarshal($result, new DateIntervalDto());
    //
    //     $now = new \DateTimeImmutable();
    //     $this->assertSame(0, $now->add($dto->interval) <=> $now->add($unmarshal->interval));
    // }

    protected function getTypeMatchers(): array
    {
        return [
            DateIntervalType::class,
        ];
    }
}
