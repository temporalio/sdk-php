<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Workflow\WorkflowExecution;

/**
 * The data needed by a client to refer to an previously invoked workflow
 * execution update process.
 *
 * @see \Temporal\Api\Update\V1\UpdateRef
 */
final class UpdateRef
{
    public function __construct(
        #[Marshal(name: 'workflow_execution')]
        public readonly WorkflowExecution $workflowExecution,
        #[Marshal(name: 'update_id')]
        public readonly string $updateId
    ) {
    }
}
