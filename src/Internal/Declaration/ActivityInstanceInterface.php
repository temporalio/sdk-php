<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\Internal\Declaration\Prototype\ActivityPrototype;

interface ActivityInstanceInterface extends InstanceInterface
{
    /**
     * @return ActivityPrototype
     */
    public function getPrototype(): ActivityPrototype;
}
