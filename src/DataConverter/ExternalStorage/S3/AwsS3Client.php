<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage\S3;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface as AwsS3ClientInterface;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class AwsS3Client implements S3ClientInterface
{
    public function __construct(
        private readonly AwsS3ClientInterface $client,
    ) {}

    public function putObject(string $bucket, string $key, string $data): void
    {
        $this->client->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => $data]);
    }

    public function objectExists(string $bucket, string $key): bool
    {
        try {
            $this->client->headObject(['Bucket' => $bucket, 'Key' => $key]);
            return true;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }

            throw $e;
        }
    }

    public function getObject(string $bucket, string $key): string
    {
        return (string) $this->client->getObject(['Bucket' => $bucket, 'Key' => $key])['Body'];
    }
}
