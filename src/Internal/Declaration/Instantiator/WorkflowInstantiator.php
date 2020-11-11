<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Instantiator;

use Temporal\Client\Internal\Declaration\Prototype\PrototypeInterface;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Declaration\WorkflowInstance;

/**
 * @template-implements InstantiatorInterface<WorkflowPrototype, WorkflowInstance>
 */
final class WorkflowInstantiator extends Instantiator
{
    /**
     * {@inheritDoc}
     */
    public function instantiate(PrototypeInterface $prototype): WorkflowInstance
    {
        assert($prototype instanceof WorkflowPrototype, 'Precondition failed');

        // TODO
        $instance = $this->getInstance($prototype);

        return new WorkflowInstance($prototype, $instance);
    }
}
