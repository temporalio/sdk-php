<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
