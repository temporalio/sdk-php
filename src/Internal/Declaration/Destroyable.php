<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

/**
 * Means that instance can be destroyed. It's preferred to destroy instance to guarantee
 * that all resources related to it will be released.
 */
interface Destroyable
{
    public function destroy(): void;
}
