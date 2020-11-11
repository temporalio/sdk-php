<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance\Instantiator;

use Temporal\Client\Internal\Instance\InstanceInterface;
use Temporal\Client\Internal\Instance\WorkflowInstance;
use Temporal\Client\Internal\Prototype\PrototypeInterface;
use Temporal\Client\Internal\Prototype\WorkflowPrototypeInterface;

/**
 * @template-implements InstantiatorInterface<WorkflowPrototypeInterface>
 */
final class WorkflowInstantiator extends Instantiator
{
    /**
     * {@inheritDoc}
     */
    public function instantiate(PrototypeInterface $prototype, string $class): InstanceInterface
    {
        assert($prototype instanceof WorkflowPrototypeInterface, 'Precondition failed');

        // TODO
        $instance = new $class();

        return new WorkflowInstance($prototype, $instance);
    }
}
