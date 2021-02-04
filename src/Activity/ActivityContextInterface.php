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
     * @see Activity::getInfo()
     *
     * @return ActivityInfo
     */
    public function getInfo(): ActivityInfo;

    /**
     * @see Activity::getInput()
     *
     * @return ValuesInterface
     */
    public function getInput(): ValuesInterface;

    /**
     * @see Activity::hasHeartbeatDetails()
     *
     * @return bool
     */
    public function hasHeartbeatDetails(): bool;

    /**
     * @see Activity::getHeartbeatDetails()
     *
     * @param Type|string $type
     * @return mixed
     */
    public function getHeartbeatDetails($type = null);

    /**
     * @see Activity::doNotCompleteOnReturn()
     *
     * @return void
     */
    public function doNotCompleteOnReturn(): void;

    /**
     * @see Activity::heartbeat()
     *
     * @param mixed $details
     */
    public function heartbeat($details): void;
}
