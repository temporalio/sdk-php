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
use Temporal\Internal\Declaration\ActivityInstance;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\PrototypeInterface;

/**
 * @template-implements InstantiatorInterface<ActivityPrototype, ActivityInstance>
 */
final class ActivityInstantiator extends Instantiator
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
    public function instantiate(PrototypeInterface $prototype): ActivityInstance
    {
        assert($prototype instanceof ActivityPrototype, 'Precondition failed');

        $instance = $this->getInstance($prototype);

        return new ActivityInstance($prototype, $this->dataConverter, $instance);
    }
}
