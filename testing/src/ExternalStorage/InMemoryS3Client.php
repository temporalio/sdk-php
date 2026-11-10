<?php

declare(strict_types=1);

namespace Temporal\Testing\ExternalStorage;

use Temporal\DataConverter\ExternalStorage\S3\S3ClientInterface;

final class InMemoryS3Client implements S3ClientInterface
{
    /** @var array<string, string> */
    public array $objects = [];

    public int $puts = 0;

    public function putObject(string $bucket, string $key, string $data): void
    {
        ++$this->puts;
        $this->objects["$bucket/$key"] = $data;
    }

    public function objectExists(string $bucket, string $key): bool
    {
        return isset($this->objects["$bucket/$key"]);
    }

    public function getObject(string $bucket, string $key): string
    {
        return $this->objects["$bucket/$key"];
    }
}
