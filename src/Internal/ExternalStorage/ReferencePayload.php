<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payload\ExternalPayloadDetails;
use Temporal\Api\Sdk\V1\ExternalStorageReference;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\Exception\DataConverterException;

/**
 * @internal
 */
final class ReferencePayload
{
    public const MARKER = '__temporal_external_storage_reference';
    public const MESSAGE_TYPE = 'temporal.api.sdk.v1.ExternalStorageReference';

    public static function isReference(Payload $payload): bool
    {
        $metadata = \iterator_to_array($payload->getMetadata());

        return isset($metadata[self::MARKER]) || self::isPlainReference($metadata);
    }

    public static function create(string $driverName, StorageDriverClaim $claim, int $originalSize): Payload
    {
        $reference = new ExternalStorageReference([
            'driver_name' => $driverName,
            'claim_data' => $claim->claimData,
        ]);

        return new Payload([
            'metadata' => [
                EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_PROTOBUF_JSON,
                EncodingKeys::METADATA_MESSAGE_TYPE => self::MESSAGE_TYPE,
                self::MARKER => 'true',
            ],
            'data' => $reference->serializeToJsonString(),
            'external_payloads' => [new ExternalPayloadDetails(['size_bytes' => $originalSize])],
        ]);
    }

    public static function parse(Payload $payload): ?ExternalStorageReference
    {
        $metadata = \iterator_to_array($payload->getMetadata());
        if (!self::isPlainReference($metadata)) {
            if (isset($metadata[self::MARKER])) {
                throw new DataConverterException(
                    'The external storage reference is encoded by a payload codec, and the PHP SDK has no payload codec to decode it.',
                );
            }

            return null;
        }

        $reference = new ExternalStorageReference();
        $reference->mergeFromJsonString($payload->getData(), true);

        return $reference;
    }

    /**
     * @param array<array-key, mixed> $metadata
     */
    private static function isPlainReference(array $metadata): bool
    {
        return ($metadata[EncodingKeys::METADATA_ENCODING_KEY] ?? null) === EncodingKeys::METADATA_ENCODING_PROTOBUF_JSON
            && ($metadata[EncodingKeys::METADATA_MESSAGE_TYPE] ?? null) === self::MESSAGE_TYPE;
    }
}
