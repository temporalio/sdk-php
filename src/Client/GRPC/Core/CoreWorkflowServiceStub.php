<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;

/**
 * @internal
 */
final class CoreWorkflowServiceStub extends WorkflowServiceClient
{
    use CoreStub;
}
