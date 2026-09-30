<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Api\Operatorservice\V1\OperatorServiceClient;

/**
 * @internal
 */
final class CoreOperatorServiceStub extends OperatorServiceClient
{
    use CoreStub;
}
