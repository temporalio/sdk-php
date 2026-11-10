<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage\S3;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverInterface;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\Internal\ExternalStorage\ObjectKey;
use Temporal\Internal\ExternalStorage\StoredObject;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class S3StorageDriver implements StorageDriverInterface
{
    public const TYPE = 'aws.s3driver';
    public const DEFAULT_NAME = 'aws.s3driver';
    public const DEFAULT_MAX_PAYLOAD_SIZE = 50 * 1024 * 1024;
    private const UNSAFE_CHARACTERS = "/[^A-Za-z0-9!\\-_.*'()]/";

    /**
     * @var \Closure(StorageDriverStoreContext, Payload): string
     */
    private readonly \Closure $bucket;

    /**
     * @param non-empty-string|\Closure(StorageDriverStoreContext, Payload): string $bucket
     * @param non-empty-string $name
     * @param positive-int $maxPayloadSize
     */
    public function __construct(
        private readonly S3ClientInterface $client,
        string|\Closure $bucket,
        private readonly string $name = self::DEFAULT_NAME,
        private readonly int $maxPayloadSize = self::DEFAULT_MAX_PAYLOAD_SIZE,
    ) {
        $this->bucket = \is_string($bucket) ? static fn(): string => $bucket : $bucket;
    }

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
            $bucket = ($this->bucket)($context, $payloads[$index]);
            $key = ObjectKey::build($context->target, $digest, self::encode(...));
            $context->limiter->permit($payloads[$index], function () use ($bucket, $key, $data): void {
                if (!$this->client->objectExists($bucket, $key)) {
                    $this->client->putObject($bucket, $key, $data);
                }
            });

            $claims[] = new StorageDriverClaim([
                'bucket' => $bucket,
                'key' => $key,
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
            $bucket = StoredObject::claimField($claim, 'bucket');
            $key = StoredObject::claimField($claim, 'key');
            $data = $context->limiter->permit($claim, fn(): string => $this->client->getObject($bucket, $key));
            $payloads[] = StoredObject::restore($data, $claim, "bucket=$bucket, key=$key");
        }

        return $payloads;
    }

    private static function encode(string $value): string
    {
        return ObjectKey::encodeBytes($value, self::UNSAFE_CHARACTERS);
    }
}
