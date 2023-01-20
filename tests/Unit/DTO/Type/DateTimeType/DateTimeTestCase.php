<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\DateTimeType;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Temporal\Internal\Marshaller\Type\DateTimeType;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;
use Temporal\Tests\Unit\DTO\Type\DateTimeType\Stub\DateTimeDto;

class DateTimeTestCase extends DTOMarshallingTestCase
{
    public function testMarshal(): void
    {
        $dto = new DateTimeDto();
        $dto->date1 = new DateTimeImmutable('2020-12-15 11:13:00');
        $dto->date2 = new DateTimeImmutable('2020-12-15 11:13:01');
        $dto->immutable = new DateTimeImmutable('2020-12-15 11:13:02');
        $dto->dateTime = new DateTime('2020-12-15 11:13:03');
        $dto->carbon = new Carbon('2020-12-15 11:13:04');
        $dto->carbonImmutable = new CarbonImmutable('2020-12-15 11:13:05');

        $result = $this->marshal($dto);

        $this->assertSame([
            'date1' => '2020-12-15T11:13:00+00:00',
            'date2' => '2020-12-15T11:13:01+00:00',
            'immutable' => '2020-12-15T11:13:02+00:00',
            'dateTime' => '2020-12-15T11:13:03+00:00',
            'carbon' => '2020-12-15T11:13:04+00:00',
            'carbonImmutable' => '2020-12-15T11:13:05+00:00',
        ], $result);
    }

    public function testUnmarshal(): void
    {
        $dto = new DateTimeDto();
        $dto->date1 = new DateTimeImmutable('2020-12-15 11:13:00');
        $dto->date2 = new DateTimeImmutable('2020-12-15 11:13:01');
        $dto->immutable = new DateTimeImmutable('2020-12-15 11:13:02');
        $dto->dateTime = new DateTime('2020-12-15 11:13:03');
        $dto->carbon = new Carbon('2020-12-15 11:13:04');
        $dto->carbonImmutable = new CarbonImmutable('2020-12-15 11:13:05');

        $result = $this->unmarshal([
            'date1' => '2020-12-15T11:13:00+00:00',
            'date2' => '2020-12-15T11:13:01+00:00',
            'immutable' => '2020-12-15T11:13:02+00:00',
            'dateTime' => '2020-12-15T11:13:03+00:00',
            'carbon' => '2020-12-15T11:13:04+00:00',
            'carbonImmutable' => '2020-12-15T11:13:05+00:00',
        ], new DateTimeDto());

        $this->assertEquals($dto, $result);
    }

    protected function getTypeMatchers(): array
    {
        return [
            DateTimeType::class,
        ];
    }
}
