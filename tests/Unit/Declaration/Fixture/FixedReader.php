<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Spiral\Attributes\ReaderInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\LocalActivityInterface;

class FixedReader implements ReaderInterface
{
    public function getClassMetadata(\ReflectionClass $class, string $name = null): iterable
    {
        return [];
    }

    public function firstClassMetadata(\ReflectionClass $class, string $name): ?object
    {
        if ($name === ActivityInterface::class) {
            return new ActivityInterface();
        }

        if ($name === LocalActivityInterface::class) {
            return new LocalActivityInterface();
        }

        return null;
    }

    public function getFunctionMetadata(\ReflectionFunctionAbstract $function, string $name = null): iterable
    {
        return [];
    }

    public function firstFunctionMetadata(\ReflectionFunctionAbstract $function, string $name): ?object
    {
        if ($name === ActivityMethod::class) {
            return new ActivityMethod();
        }

        return null;
    }

    public function getPropertyMetadata(\ReflectionProperty $property, string $name = null): iterable
    {
        return [];
    }

    public function firstPropertyMetadata(\ReflectionProperty $property, string $name): ?object
    {
        return null;
    }

    public function getConstantMetadata(\ReflectionClassConstant $constant, string $name = null): iterable
    {
        return [];
    }

    public function firstConstantMetadata(\ReflectionClassConstant $constant, string $name): ?object
    {
        return null;
    }

    public function getParameterMetadata(\ReflectionParameter $parameter, string $name = null): iterable
    {
        return [];
    }

    public function firstParameterMetadata(\ReflectionParameter $parameter, string $name): ?object
    {
        return null;
    }
}
