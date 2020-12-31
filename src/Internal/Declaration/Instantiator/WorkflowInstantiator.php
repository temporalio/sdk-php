<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Instantiator;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Declaration\Prototype\PrototypeInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;

/**
 * @template-implements InstantiatorInterface<WorkflowPrototype, WorkflowInstance>
 */
final class WorkflowInstantiator extends Instantiator
{
    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function instantiate(PrototypeInterface $prototype): WorkflowInstance
    {
        assert($prototype instanceof WorkflowPrototype, 'Precondition failed');

        // TODO
        $instance = $this->getInstance($prototype);

        return new WorkflowInstance($prototype, $this->dataConverter, $instance);
    }
}
