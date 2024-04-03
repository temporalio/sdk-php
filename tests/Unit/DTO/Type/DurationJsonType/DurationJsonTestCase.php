<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\DurationJsonType;

use Carbon\CarbonInterval;
use Temporal\Internal\Marshaller\Type\DurationJsonType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Tests\Unit\DTO\AbstractDTOMarshalling;
use Temporal\Tests\Unit\DTO\Type\DurationJsonType\Stub\DurationJsonDto;

class DurationJsonTestCase extends AbstractDTOMarshalling
{
    public function testMarshalAndUnmarshalDuration(): void
    {
        $dto = new DurationJsonDto();
        $dto->duration = DateInterval::parse(1);

        $result = $this->marshal($dto);
        $unmarshal = $this->unmarshal($result, new DurationJsonDto());

        self::assertInstanceOf(CarbonInterval::class, $unmarshal->duration);
    }

    protected function getTypeMatchers(): array
    {
        return [
            DurationJsonType::class,
        ];
    }
}
