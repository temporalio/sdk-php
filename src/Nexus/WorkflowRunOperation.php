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
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;

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

        try {
            $decoded = WorkflowRunOperationToken::load($operationToken);
        } catch (\InvalidArgumentException $e) {
            throw HandlerException::create(ErrorType::BadRequest, 'failed to parse operation token', $e);
        }

        $client->newUntypedRunningWorkflowStub($decoded->workflowId)->cancel();
    }
}
