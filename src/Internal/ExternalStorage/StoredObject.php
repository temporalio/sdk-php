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
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\Exception\DataConverterException;

/**
 * @internal
 */
final class StoredObject
{
    public const HASH_ALGORITHM = 'sha256';

    /**
     * @param list<Payload> $payloads
     * @return list<array{string, string}>
     */
    public static function serialize(array $payloads, int $maxPayloadSize): array
    {
        $objects = [];
        foreach ($payloads as $payload) {
            $data = $payload->serializeToString();
            if (\strlen($data) > $maxPayloadSize) {
                throw new DataConverterException(
                    \sprintf('Payload size %d exceeds maximum %d.', \strlen($data), $maxPayloadSize),
                );
            }

            $objects[] = [$data, \hash(self::HASH_ALGORITHM, $data)];
        }

        return $objects;
    }

    public static function restore(string $data, StorageDriverClaim $claim, string $location): Payload
    {
        $algorithm = self::claimField($claim, 'hash_algorithm');
        if ($algorithm !== self::HASH_ALGORITHM) {
            throw new DataConverterException("Unsupported hash algorithm '$algorithm'.");
        }

        $expected = self::claimField($claim, 'hash_value');
        $actual = \hash(self::HASH_ALGORITHM, $data);
        if (!\hash_equals($expected, $actual)) {
            throw new DataConverterException(\sprintf(
                'Integrity check failed [%s]: expected hash %s, got %s.',
                $location,
                $expected,
                $actual,
            ));
        }

        $payload = new Payload();
        $payload->mergeFromString($data);

        return $payload;
    }

    public static function claimField(StorageDriverClaim $claim, string $field): string
    {
        return $claim->claimData[$field] ?? throw new DataConverterException("Claim missing field '$field'.");
    }
}
