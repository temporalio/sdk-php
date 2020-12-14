<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

interface ActivityContextInterface
{
    /**
     * Information about activity invocation and the caller workflow.
     *
     * @return ActivityInfo
     */
    public function getInfo(): ActivityInfo;

    /**
     * Returns the arguments passed to the activity.
     *
     * @return array
     */
    public function getArguments(): array;

    /**
     * If this method is called during an activity execution then activity is
     * not going to complete when its method returns. It is expected to be
     * completed asynchronously using {@see ConnectionInterface::call()}.
     *
     * @return void
     */
    public function doNotCompleteOnReturn(): void;

    /**
     * @return bool
     */
    public function isDoNotCompleteOnReturn(): bool;
}
