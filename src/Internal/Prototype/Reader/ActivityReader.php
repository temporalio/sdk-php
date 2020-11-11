<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Prototype\Reader;

use Temporal\Client\Activity\Meta\ActivityInterface;
use Temporal\Client\Activity\Meta\ActivityMethod;
use Temporal\Client\Internal\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Prototype\ActivityPrototypeInterface;

/**
 * @template-implements ReaderInterface<ActivityPrototypeInterface>
 */
class ActivityReader extends Reader
{
    /**
     * @param string $class
     * @return ActivityPrototypeInterface[]
     * @throws \ReflectionException
     */
    public function fromClass(string $class): iterable
    {
        $reflection = new \ReflectionClass($class);
        $interface = $this->getActivityInterface($reflection);

        foreach ($this->annotatedMethods($reflection, ActivityMethod::class) as $method => $handler) {
            $method->name ??= $handler->getName();

            yield new ActivityPrototype($interface, $method, $handler);
        }
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
