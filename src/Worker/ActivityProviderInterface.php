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

interface ActivityProviderInterface
{
    /**
     * @param string $name
     * @return ActivityInterface|null
     */
    public function findActivity(string $name): ?ActivityInterface;
}
