<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

/**
 * Used to complete asynchronously activities that called {@link
 * ActivityContext->doNotCompleteOnReturn()}.
 *
 * <p>Use {@link WorkflowClient->newActivityCompletionClient()} to create an instance.
 */
interface ActivityCompletionClientInterface
{
    /**
     * @param string $workflowId
     * @param string|null $runId
     * @param string $activityId
     * @param mixed $result
     */
    public function complete(string $workflowId, ?string $runId, string $activityId, $result = null): void;

    /**
     * @param string $taskToken
     * @param mixed $result
     */
    public function completeByToken(string $taskToken, $result = null): void;

    /**
     * @param string $workflowId
     * @param string|null $runId
     * @param string $activityId
     * @param \Throwable $error
     */
    public function completeExceptionally(
        string $workflowId,
        ?string $runId,
        string $activityId,
        \Throwable $error
    ): void;

    /**
     * @param string $taskToken
     * @param \Throwable $error
     */
    public function completeExceptionallyByToken(string $taskToken, \Throwable $error): void;

    /**
     * @param string $workflowId
     * @param string|null $runId
     * @param string $activityId
     * @param $details
     */
    public function reportCancellation(
        string $workflowId,
        ?string $runId,
        string $activityId,
        $details = null
    ): void;

    /**
     * @param string $taskToken
     * @param $details
     */
    public function reportCancellationByToken(string $taskToken, $details = null): void;

    /**
     * @param string $workflowId
     * @param string|null $runId
     * @param string $activityId
     * @param mixed $details
     *
     * @throw ActivityCanceledException
     */
    public function recordHeartbeat(
        string $workflowId,
        ?string $runId,
        string $activityId,
        $details = null
    );

    /**
     * @param string $taskToken
     * @param mixed $details
     *
     * @throw ActivityCanceledException
     */
    public function recordHeartbeatByToken(string $taskToken, $details = null);
}
