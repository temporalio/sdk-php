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
     * @return array
     */
    public function getArguments(): array;

    /**
     * Call given method to enable external activity completion using activity ID or task token.
     */
    public function doNotCompleteOnReturn(): void;

    /**
     * @return ActivityInfo
     */
    public function getInfo(): ActivityInfo;
}
