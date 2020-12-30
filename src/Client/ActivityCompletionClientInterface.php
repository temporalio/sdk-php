<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

interface ActivityCompletionClientInterface
{
    /**
     * @param string $wid
     * @param string|null $runId
     * @param string $activityId
     * @param mixed $result
     */
    public function complete(string $wid, ?string $runId, string $activityId, $result): void;

    /**
     * @param string $wid
     * @param string|null $runId
     * @param string $activityId
     * @param \Throwable $error
     */
    public function completeExceptionally(string $wid, ?string $runId, string $activityId, \Throwable $error): void;

    /**
     * @param string $taskToken
     * @param mixed $result
     */
    public function completeByToken(string $taskToken, $result): void;

    /**
     * @param string $taskToken
     * @param \Throwable $error
     */
    public function completeExceptionallyByToken(string $taskToken, \Throwable $error): void;

    /**
     * @param string $wid
     * @param string|null $runId
     * @param string $activityId
     * @param $details
     */
    public function reportCancellation(string $wid, ?string $runId, string $activityId, $details): void;

    /**
     * @param string $taskToken
     * @param $details
     */
    public function reportCancellationByToken(string $taskToken, $details): void;

    /**
     * @param string $wid
     * @param string|null $runId
     * @param string $activityId
     * @param mixed $details
     * @return bool
     */
    public function heartbeat(string $wid, ?string $runId, string $activityId, $details): bool;

    /**
     * @param string $taskToken
     * @param mixed $details
     * @return bool
     */
    public function heartbeatByToken(string $taskToken, $details): bool;
}
