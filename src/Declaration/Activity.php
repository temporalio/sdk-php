<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Declaration;

use Temporal\Client\Meta\ActivityMethod;
use Temporal\Client\Meta\ReaderInterface;

final class Activity extends HandledDeclaration implements ActivityInterface
{
    /**
     * @param object $object
     * @param ReaderInterface $reader
     * @return ActivityInterface[]
     */
    public static function fromObject(object $object, ReaderInterface $reader): iterable
    {
        $reflection = new \ReflectionObject($object);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            /** @var ActivityMethod $meta */
            foreach ($reader->getMethodMetadata($method, ActivityMethod::class) as $meta) {
                $name = $meta->name ?? self::createActivityName($reflection, $method);

                yield new self($name, $method->getClosure($object));
            }
        }
    }

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @return string
     */
    private static function createActivityName(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        return $class->getName() . '::' . $method->getName();
    }
}
