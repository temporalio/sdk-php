<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage\S3;

/**
 * @experimental This API is experimental and may change in the future.
 */
interface S3ClientInterface
{
    public function putObject(string $bucket, string $key, string $data): void;

    public function objectExists(string $bucket, string $key): bool;

    public function getObject(string $bucket, string $key): string;
}
