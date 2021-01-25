<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Repository;

/**
 * @psalm-type Identifier = string|int
 */
interface Identifiable
{
    /**
     * @return Identifier
     */
    public function getID();
}
