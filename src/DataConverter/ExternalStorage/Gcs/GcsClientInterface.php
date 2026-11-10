<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage\Gcs;

/**
 * @experimental This API is experimental and may change in the future.
 */
interface GcsClientInterface
{
    public function putObject(string $bucket, string $objectName, string $data): void;

    public function objectExists(string $bucket, string $objectName): bool;

    public function getObject(string $bucket, string $objectName): string;
}
