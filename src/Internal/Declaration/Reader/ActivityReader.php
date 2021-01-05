<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use JetBrains\PhpStorm\Pure;
use ReflectionFunctionAbstract as ReflectionFunction;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;

/**
 * @template-extends Reader<ActivityPrototype>
 */
class ActivityReader extends Reader
{
    /**
     * @param string $class
     * @return ActivityPrototype[]
     * @throws \ReflectionException
     */
    public function fromClass(string $class): array
    {
        $result = [];

        $reflection = new \ReflectionClass($class);
        $interface = $this->getActivityInterface($reflection);

        foreach ($this->annotatedMethods($reflection, ActivityMethod::class) as $method => $handler) {
            $name = $this->createActivityName($handler, $method, $interface);

            $result[] = new ActivityPrototype($name, $handler, $reflection);
        }

        return $result;
    }

    /**
     * @param ReflectionFunction $fn
     * @param ActivityMethod $m
     * @param ActivityInterface $interface
     * @return string
     */
    private function createActivityName(ReflectionFunction $fn, ActivityMethod $m, ActivityInterface $interface): string
    {
        return $interface->prefix . ($m->name ?? $fn->getName());
    }

    /**
     * @param \ReflectionClass $class
     * @return ActivityInterface
     */
    private function getActivityInterface(\ReflectionClass $class): ActivityInterface
    {
        $attributes = $this->reader->getClassMetadata($class, ActivityInterface::class);

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($attributes as $attribute) {
            return $attribute;
        }

        return new ActivityInterface();
    }
}
