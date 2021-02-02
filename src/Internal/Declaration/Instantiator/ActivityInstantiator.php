<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Instantiator;

use Temporal\Internal\Declaration\ActivityInstance;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\PrototypeInterface;

/**
 * @template-implements InstantiatorInterface<ActivityPrototype, ActivityInstance>
 */
final class ActivityInstantiator extends Instantiator
{
    /**
     * {@inheritDoc}
     */
    public function instantiate(PrototypeInterface $prototype): ActivityInstance
    {
        assert($prototype instanceof ActivityPrototype, 'Precondition failed');

        return new ActivityInstance($prototype, $this->getInstance($prototype));
    }
}
