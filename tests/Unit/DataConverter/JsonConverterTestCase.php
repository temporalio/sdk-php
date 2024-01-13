<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use Ramsey\Uuid\Uuid;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\Tests\Unit\UnitTestCase;

/**
 * @group unit
 * @group data-converter
 */
class JsonConverterTestCase extends UnitTestCase
{
    protected function create(): PayloadConverterInterface
    {
        return new JsonConverter();
    }

    public function testUuidToPayload(): void
    {
        $converter = $this->create();

        $dto = Uuid::uuid4();

        $payload = $converter->toPayload($dto);

        $this->assertNotNull($payload);
        $this->assertSame(
            \json_encode((string)$dto),
            $payload->getData(),
        );
    }

    /**
     * @throws \JsonException
     */
    public function testNullFromPayload(): void
    {
        $converter = $this->create();

        $payload = new Payload();
        $payload->setMetadata([EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_JSON]);
        $payload->setData(json_encode(null, JSON_THROW_ON_ERROR));

        $value = $converter->fromPayload($payload, new Type(Type::TYPE_STRING, true));

        $this->assertNull($value);
    }
}
