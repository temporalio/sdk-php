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

final class ActivityInstance extends Instance implements ActivityInstanceInterface
{
    /**
     * @param ActivityPrototype $prototype
     * @param object $context
     */
    public function __construct(
        ActivityPrototype $prototype,
        object $context,
    ) {
        parent::__construct($prototype, $context);
    }
}
