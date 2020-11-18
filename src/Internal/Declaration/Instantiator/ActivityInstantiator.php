<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Instantiator;

use Temporal\Client\Internal\Declaration\ActivityInstance;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\PrototypeInterface;

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

        // TODO
        $instance = $this->getInstance($prototype);

        return new ActivityInstance($prototype, $instance);
    }
}
