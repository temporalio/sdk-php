<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\DurationJsonType;

use Temporal\Internal\Marshaller\Type\DurationJsonType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Tests\Unit\DTO\AbstractDTOMarshalling;
use Temporal\Tests\Unit\DTO\Type\DurationJsonType\Stub\DurationJsonDto;

class DurationJsonTestCase extends AbstractDTOMarshalling
{
    public function testMarshalAndUnmarshalDuration(): void
    {
        $dto = new DurationJsonDto();
        $dto->duration = DateInterval::parse(100);
        $dto->durationProto = DateInterval::parse(12000);

        $result = $this->marshal($dto);
        $unmarshal = $this->unmarshal($result, new DurationJsonDto());

        self::assertInstanceOf(\DateInterval::class, $unmarshal->duration);
        self::assertInstanceOf(\DateInterval::class, $unmarshal->durationProto);
        self::assertSame('0 100000', $unmarshal->duration->format('%s %f'));
        self::assertSame('12 0', $unmarshal->durationProto->format('%s %f'));
    }

    public function testUnmarshallEmptyDuration(): void
    {
        $result = ['duration' => null, 'duration_proto' => null];
        $unmarshal = $this->unmarshal($result, new DurationJsonDto());

        self::assertSame('0.0', $unmarshal->duration->format('%s.%f'));
        self::assertSame('0.0', $unmarshal->durationProto->format('%s.%f'));
    }

    protected function getTypeMatchers(): array
    {
        return [
            DurationJsonType::class,
        ];
    }
}
