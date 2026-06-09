<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Internal\WorkflowRunOperationToken;
use Temporal\Nexus\Exception\InvalidArgumentException;

/**
 * Helpers that back a Nexus operation with a Temporal workflow run.
 *
 * Return a {@see WorkflowHandle} from your #[AsyncOperation] method to start the
 * backing workflow; use ::cancel() inside #[OperationCancel] to cancel it.
 */
final class WorkflowRunOperation
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Cancel the workflow corresponding to the given operation token.
     *
     * Decodes the token and asks the workflow client to cancel by workflow ID.
     */
    public static function cancel(string $operationToken): void
    {
        $client = Nexus::getWorkflowClient();
        $info = Nexus::getOperationContext();
        $decoded = WorkflowRunOperationToken::load($operationToken);

        if ($decoded->namespace !== '' && $decoded->namespace !== $info->namespace) {
            throw new InvalidArgumentException(\sprintf(
                'workflow run token namespace "%s" does not match handler namespace "%s"',
                $decoded->namespace,
                $info->namespace,
            ));
        }

        $stub = $client->newUntypedRunningWorkflowStub($decoded->workflowId);
        $stub->cancel();
    }
}
