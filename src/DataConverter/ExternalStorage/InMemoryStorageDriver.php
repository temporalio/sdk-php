<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage;

use Temporal\Internal\ExternalStorage\StoredObject;
use Temporal\Exception\DataConverterException;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class InMemoryStorageDriver implements StorageDriverInterface
{
    public const TYPE = 'temporal.memorydriver';
    public const DEFAULT_NAME = 'temporal.memorydriver';
    public const DEFAULT_MAX_PAYLOAD_SIZE = 50 * 1024 * 1024;

    /**
     * @var array<string, string>
     */
    private array $objects = [];

    /**
     * @param non-empty-string $name
     * @param positive-int $maxPayloadSize
     */
    public function __construct(
        private readonly string $name = self::DEFAULT_NAME,
        private readonly int $maxPayloadSize = self::DEFAULT_MAX_PAYLOAD_SIZE,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function store(StorageDriverStoreContext $context, array $payloads): array
    {
        $claims = [];
        foreach (StoredObject::serialize($payloads, $this->maxPayloadSize) as $index => [$data, $digest]) {
            $context->limiter->permit($payloads[$index], function () use ($digest, $data): void {
                $this->objects[$digest] = $data;
            });

            $claims[] = new StorageDriverClaim([
                'hash_algorithm' => StoredObject::HASH_ALGORITHM,
                'hash_value' => $digest,
            ]);
        }

        return $claims;
    }

    public function retrieve(StorageDriverRetrieveContext $context, array $claims): array
    {
        $payloads = [];
        foreach ($claims as $claim) {
            $digest = StoredObject::claimField($claim, 'hash_value');
            $data = $context->limiter->permit(
                $claim,
                fn(): string => $this->objects[$digest]
                    ?? throw new DataConverterException("No stored object with hash '$digest'."),
            );
            $payloads[] = StoredObject::restore($data, $claim, "hash=$digest");
        }

        return $payloads;
    }
}
