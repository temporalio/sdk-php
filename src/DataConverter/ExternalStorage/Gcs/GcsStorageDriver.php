<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage\Gcs;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\StorageDriverActivityInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverInterface;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverTargetInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;
use Temporal\Internal\ExternalStorage\ObjectKey;
use Temporal\Internal\ExternalStorage\StoredObject;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class GcsStorageDriver implements StorageDriverInterface
{
    public const TYPE = 'gcp.gcsdriver';
    public const DEFAULT_NAME = 'gcp.gcsdriver';
    public const DEFAULT_MAX_PAYLOAD_SIZE = 50 * 1024 * 1024;
    private const MAX_OBJECT_NAME_BYTES = 1024;
    private const UNSAFE_CHARACTERS = '/[\x00-\x1F\x7F#\[\]*?:"<>|\/%]|\xC2[\x80-\x9F]/';

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
        private readonly GcsClientInterface $client,
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
            $objectName = self::objectName($context->target, $digest);
            $context->limiter->permit($payloads[$index], function () use ($bucket, $objectName, $data): void {
                if (!$this->client->objectExists($bucket, $objectName)) {
                    $this->client->putObject($bucket, $objectName, $data);
                }
            });

            $claims[] = new StorageDriverClaim([
                'bucket' => $bucket,
                'object_name' => $objectName,
                'key' => $objectName,
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
            $objectName = $claim->claimData['object_name'] ?? StoredObject::claimField($claim, 'key');
            $data = $context->limiter->permit(
                $claim,
                fn(): string => $this->client->getObject($bucket, $objectName),
            );
            $payloads[] = StoredObject::restore($data, $claim, "bucket=$bucket, object_name=$objectName");
        }

        return $payloads;
    }

    private static function objectName(?StorageDriverTargetInfo $target, string $digest): string
    {
        $name = ObjectKey::build($target, $digest, self::encode(...));
        if (\strlen($name) <= self::MAX_OBJECT_NAME_BYTES) {
            return $name;
        }

        $namespace = $target instanceof StorageDriverWorkflowInfo || $target instanceof StorageDriverActivityInfo
            ? $target->namespace
            : '';

        return ObjectKey::VERSION . '/ns/' . self::encode($namespace) . ObjectKey::digestSegment($digest);
    }

    private static function encode(string $value): string
    {
        $encoded = ObjectKey::encodeBytes($value, self::UNSAFE_CHARACTERS);

        return match ($encoded) {
            '.' => '%2E',
            '..' => '%2E%2E',
            default => $encoded,
        };
    }
}
