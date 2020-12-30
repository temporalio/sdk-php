<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Worker\Command\ErrorResponse;
use Temporal\Worker\Transport\RpcConnectionInterface;

class ActivityCompletionClient implements ActivityCompletionClientInterface
{
    /**
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @var string
     */
    private string $namespace;

    /**
     * @param RpcConnectionInterface $rpc
     * @param string $namespace
     */
    public function __construct(RpcConnectionInterface $rpc, string $namespace)
    {
        $this->rpc = $rpc;
        $this->namespace = $namespace;
    }

    /**
     * {@inheritDoc}
     */
    public function completeExceptionally(string $wid, ?string $runId, string $activityId, \Throwable $error): void
    {
        $this->complete($wid, $runId, $activityId, [
            'error' => ErrorResponse::exceptionToArray($error),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function complete(string $wid, ?string $runId, string $activityId, $result): void
    {
        $this->rpc->call('temporal.CompleteActivityByID', [
            'namespace'   => $this->namespace,
            'wid'         => $wid,
            'rid'         => $runId,
            'activity_id' => $activityId,
            'result'      => $result,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function completeExceptionallyByToken(string $taskToken, \Throwable $error): void
    {
        $this->completeByToken($taskToken, [
            'error' => ErrorResponse::exceptionToArray($error),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function completeByToken(string $taskToken, $result): void
    {
        $this->rpc->call('temporal.CompleteActivity', [
            'taskToken' => $taskToken,
            'result'    => $result,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function reportCancellation(string $wid, ?string $runId, string $activityId, $details): void
    {
        $this->rpc->call('temporal.ReportActivityCancellationByID', [
            'namespace'   => $this->namespace,
            'wid'         => $wid,
            'rid'         => $runId,
            'activity_id' => $activityId,
            'details'     => $details,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function reportCancellationByToken(string $taskToken, $details): void
    {
        $this->rpc->call('temporal.ReportActivityCancellation', [
            'taskToken' => $taskToken,
            'details'   => $details,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function heartbeat(string $wid, ?string $runId, string $activityId, $details): bool
    {
        return $this->rpc->call('temporal.RecordActivityHeartbeatByID', [
            'namespace'   => $this->namespace,
            'wid'         => $wid,
            'rid'         => $runId,
            'activity_id' => $activityId,
            'details'     => $details,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function heartbeatByToken(string $taskToken, $details): bool
    {
        return $this->rpc->call('temporal.RecordActivityHeartbeat', [
            'TaskToken' => $taskToken,
            'Details'   => $details,
        ]);
    }
}
