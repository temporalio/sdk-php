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

interface ActivityContextInterface
{
    /**
     * Returns information about current activity execution.
     *
     * @see Activity::getInfo()
     *
     * @return ActivityInfo
     */
    public function getInfo(): ActivityInfo;

    /**
     * Returns activity execution input arguments.
     *
     * @see Activity::getInput()
     *
     * @return ValuesInterface
     */
    public function getInput(): ValuesInterface;

    /**
     * Check if the heartbeat's first argument has been passed.
     *
     * @see Activity::hasHeartbeatDetails()
     *
     * @return bool
     */
    public function hasHeartbeatDetails(): bool;

    /**
     * Returns the payload passed into the last heartbeat.
     *
     * @see Activity::getHeartbeatDetails()
     *
     * @param Type|string $type
     * @return mixed
     */
    public function getHeartbeatDetails($type = null);

    /**
     * Marks the activity as incomplete for asynchronous completion.
     *
     * @see Activity::doNotCompleteOnReturn()
     *
     * @return void
     */
    public function doNotCompleteOnReturn(): void;

    /**
     * Use to notify workflow that activity execution is alive.
     *
     * @see Activity::heartbeat()
     *
     * @param mixed $details
     */
    public function heartbeat($details): void;
}
