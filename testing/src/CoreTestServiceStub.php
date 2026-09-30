<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Api\Testservice\V1\TestServiceClient;
use Temporal\Client\GRPC\Core\CoreStub;

/**
 * @internal
 */
final class CoreTestServiceStub extends TestServiceClient
{
    use CoreStub;
}
