<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

interface RecursiveAttributeReducerInterface
{
    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @param object|null $interface
     * @param array $attributes
     * @return mixed
     */
    public function root(
        \ReflectionClass $class,
        \ReflectionMethod $method,
        ?object $interface,
        array $attributes
    );

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @param object|null $interface
     * @param array $attributes
     * @return mixed
     */
    public function each(
        \ReflectionClass $class,
        \ReflectionMethod $method,
        ?object $interface,
        array $attributes
    );
}
