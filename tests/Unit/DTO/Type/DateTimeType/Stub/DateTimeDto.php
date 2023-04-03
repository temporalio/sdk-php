<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\DateTimeType\Stub;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\DateTimeType;

class DateTimeDto
{
    #[Marshal(type: DateTimeType::class)]
    public DateTimeInterface $date1;

    #[Marshal(type: DateTimeType::class, of: DateTimeImmutable::class, nullable: true)]
    public ?DateTimeImmutable $date2;

    public DateTimeImmutable $immutable;

    public DateTime $dateTime;

    public ?Carbon $carbon;

    public ?CarbonImmutable $carbonImmutable;
}
