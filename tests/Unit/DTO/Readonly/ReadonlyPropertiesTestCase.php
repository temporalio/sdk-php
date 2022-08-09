<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Readonly;

use Temporal\DataConverter\DataConverter;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;

class ReadonlyPropertiesTestCase extends DTOMarshallingTestCase
{
    public function testMarshalling(): void
    {
        if (PHP_VERSION_ID < 80104) {
            $this->markTestSkipped();
        }

        $dto = new ReadonlyPropertiesDTO(
            'promotedString',
            'secondPromotedString',
            'propertiesString',
        );

        $reflection = new \ReflectionClass(ReadonlyPropertiesDTO::class);

        $converter = DataConverter::createDefault();

        $payload = $converter->toPayload($dto);
        $fromPayload = $converter->fromPayload($payload, ReadonlyPropertiesDTO::class);

        self::assertEquals($dto, $fromPayload);

        $marshaled = $this->marshal($dto);
        $unmarshaled = $this->unmarshal(
            $marshaled,
            $reflection->newInstanceWithoutConstructor()
        );

        self::assertEquals($dto, $unmarshaled);
    }
}
