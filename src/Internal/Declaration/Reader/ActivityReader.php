<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Reader;

use ReflectionFunctionAbstract as ReflectionFunction;
use Temporal\Client\Activity\Meta\ActivityInterface;
use Temporal\Client\Activity\Meta\ActivityMethod;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;

/**
 * @template-extends Reader<ActivityPrototype>
 */
class ActivityReader extends Reader
{
    /**
     * {@inheritDoc}
     */
    public function fromClass(string $class): iterable
    {
        $reflection = new \ReflectionClass($class);
        $interface = $this->getActivityInterface($reflection);

        foreach ($this->annotatedMethods($reflection, ActivityMethod::class) as $method => $handler) {
            $name = $this->createActivityName($handler, $method, $interface);

            yield new ActivityPrototype($name, $handler, $reflection);
        }
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
