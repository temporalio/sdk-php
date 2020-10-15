<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Worker\Declaration\HandledDeclaration;

final class ActivityDeclaration extends HandledDeclaration implements ActivityDeclarationInterface
{
    /**
     * @param object $object
     * @param ReaderInterface $reader
     * @return ActivityDeclarationInterface[]
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
