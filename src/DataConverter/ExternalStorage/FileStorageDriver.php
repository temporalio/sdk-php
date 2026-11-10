<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage;

use Temporal\Exception\DataConverterException;
use Temporal\Internal\ExternalStorage\ObjectKey;
use Temporal\Internal\ExternalStorage\StoredObject;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class FileStorageDriver implements StorageDriverInterface
{
    public const TYPE = 'temporal.filedriver';
    public const DEFAULT_NAME = 'temporal.filedriver';
    public const DEFAULT_MAX_PAYLOAD_SIZE = 50 * 1024 * 1024;
    private const UNSAFE_CHARACTERS = '/[^A-Za-z0-9\-_.]/';

    /**
     * @param non-empty-string $directory
     * @param non-empty-string $name
     * @param positive-int $maxPayloadSize
     */
    public function __construct(
        private readonly string $directory,
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
            $path = ObjectKey::build($context->target, $digest, self::encode(...));
            $context->limiter->permit($payloads[$index], fn() => $this->write($path, $data));

            $claims[] = new StorageDriverClaim([
                'path' => $path,
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
            $path = StoredObject::claimField($claim, 'path');
            $data = $context->limiter->permit($claim, fn(): string => $this->read($path));
            $payloads[] = StoredObject::restore($data, $claim, "path=$path");
        }

        return $payloads;
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

    private function write(string $path, string $data): void
    {
        $file = $this->directory . '/' . $path;
        if (\is_file($file)) {
            return;
        }

        $directory = \dirname($file);
        if (!\is_dir($directory) && !@\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new DataConverterException("Unable to create the directory '$directory'.");
        }

        $temporary = $file . '.' . \bin2hex(\random_bytes(8)) . '.tmp';
        if (@\file_put_contents($temporary, $data) !== \strlen($data) || !@\rename($temporary, $file)) {
            @\unlink($temporary);
            throw new DataConverterException("Unable to write the file '$file'.");
        }
    }

    private function read(string $path): string
    {
        if (\str_contains('/' . $path . '/', '/../')) {
            throw new DataConverterException("Invalid stored object path '$path'.");
        }

        $file = $this->directory . '/' . $path;
        $data = \is_file($file) ? @\file_get_contents($file) : false;
        if ($data === false) {
            throw new DataConverterException("Unable to read the file '$file'.");
        }

        return $data;
    }
}
