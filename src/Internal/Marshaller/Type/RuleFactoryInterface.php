<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Temporal\Internal\Marshaller\MarshallingRule;

/**
 * The type can detect the property type information from its reflection.
 *
 * @extends TypeInterface<mixed>
 */
interface RuleFactoryInterface extends TypeInterface
{
    /**
     * Make a marshalling rule for the given property.
     *
     * @param \ReflectionProperty $property
     *
     * @return null|MarshallingRule
     */
    public static function makeRule(\ReflectionProperty $property): ?MarshallingRule;
}
