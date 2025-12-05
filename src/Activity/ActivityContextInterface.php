<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\Activity;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Client\ActivityCompletionException;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Exception\Client\ActivityResetException;

interface ActivityContextInterface
{
    /**
     * Returns information about current activity execution.
     *
     * @see Activity::getInfo()
     */
    public function getInfo(): ActivityInfo;

    /**
     * Returns activity execution input arguments.
     *
     * @see Activity::getInput()
     */
    public function getInput(): ValuesInterface;

    /**
     * Check if the heartbeat's first argument has been passed.
     *
     * @see Activity::hasHeartbeatDetails()
     */
    public function hasHeartbeatDetails(): bool;

    /**
     * Returns the payload passed into the last heartbeat.
     *
     * @see Activity::getHeartbeatDetails()
     *
     * @param Type|string $type
     */
    public function getLastHeartbeatDetails($type = null): mixed;

    /**
     * Marks the activity as incomplete for asynchronous completion.
     *
     * @see Activity::doNotCompleteOnReturn()
     */
    public function doNotCompleteOnReturn(): void;

    /**
     * Use to notify workflow that activity execution is alive.
     *
     * @throws ActivityCompletionException
     * @throws ActivityCanceledException
     * @throws ActivityPausedException
     * @throws ActivityResetException
     *
     * @see Activity::heartbeat()
     *
     */
    public function heartbeat(mixed $details): void;

    /**
     * Cancellation details of the current activity, if any.
     *
     * Once set, cancellation details do not change.
     *
     * @see Activity::getCancellationDetails()
     */
    public function getCancellationDetails(): ?ActivityCancellationDetails;

    /**
     * Get the currently running activity instance.
     *
     * @see Activity::getInstance()
     */
    public function getInstance(): object;
}
