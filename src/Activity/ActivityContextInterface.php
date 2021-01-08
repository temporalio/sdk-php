<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\Worker\Transport\RpcConnectionInterface;

interface ActivityContextInterface
{
    /**
     * Information about activity invocation and the caller workflow.
     *
     * @return ActivityInfo
     */
    public function getInfo(): ActivityInfo;

    /**
     * If this method is called during an activity execution then activity is
     * not going to complete when its method returns. It is expected to be
     * completed asynchronously using {@see RpcConnectionInterface::call()}.
     *
     * @return void
     */
    public function doNotCompleteOnReturn(): void;

    /**
     * @return bool
     */
    public function isDoNotCompleteOnReturn(): bool;

    /**
     * Use to notify Simple Workflow that activity execution is alive.
     *
     * @param mixed $details In case of activity timeout details are returned
     *  as a field of the exception thrown.
     *
     * @return mixed
     */
    public function heartbeat($details);
}
