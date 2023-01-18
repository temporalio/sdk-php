<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

/**
 * Defines ability for {@see TypeFactoryInterface} to produce {@see MarshallingRule}
 * using {@see Type\RuleFactoryInterface}.
 */
interface RuleFactoryInterface extends TypeFactoryInterface
{
    /**
     * @param \ReflectionProperty $property
     *
     * @return null|MarshallingRule
     */
    public function makeRule(\ReflectionProperty $property): ?MarshallingRule;
}
