<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Api\Cloud\Cloudservice\V1\CloudServiceClient;

/**
 * @internal
 */
final class CoreCloudServiceStub extends CloudServiceClient
{
    use CoreStub;
}
