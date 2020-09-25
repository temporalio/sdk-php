<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Temporal\Client\Declaration\ActivityInterface;

interface MutableActivityProviderInterface extends ActivityProviderInterface
{
    /**
     * @param object $activity
     * @param bool $overwrite
     */
    public function addActivity(object $activity, bool $overwrite = false): void;

    /**
     * @param ActivityInterface $activity
     * @param bool $overwrite
     */
    public function addActivityDeclaration(ActivityInterface $activity, bool $overwrite = false): void;
}
