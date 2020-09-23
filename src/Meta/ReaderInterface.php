<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Meta;

interface ReaderInterface
{
    /**
     * @param \ReflectionClass $class
     * @param string|null $name
     * @return object[]
     */
    public function getClassMetadata(\ReflectionClass $class, string $name = null): iterable;

    /**
     * @param \ReflectionMethod $method
     * @param string|null $name
     * @return object[]
     */
    public function getMethodMetadata(\ReflectionMethod $method, string $name = null): iterable;

    /**
     * @param \ReflectionProperty $property
     * @param string|null $name
     * @return object[]
     */
    public function getPropertyMetadata(\ReflectionProperty $property, string $name = null): iterable;
}
