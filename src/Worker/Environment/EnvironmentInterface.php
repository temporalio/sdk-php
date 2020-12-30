<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Environment;

use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;

interface EnvironmentInterface
{
    /**
     * @return CarbonTimeZone
     */
    public function getTimeZone(): CarbonTimeZone;

    /**
     * @return CarbonInterface
     */
    public function now(): CarbonInterface;

    /**
     * @return bool
     */
    public function isReplaying(): bool;
}
