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
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;

/**
 * @template-extends Reader<ActivityPrototype>
 */
class ActivityReader extends Reader
{
    /**
     * @var string
     */
    private const ERROR_BAD_DECLARATION =
        'An Activity method can only be a public non-static method, ' .
        'but %s::%s() does not meet these criteria';

    /**
     * @var string
     */
    private const ERROR_DECLARATION_DUPLICATION =
        'An Activity method %s::%s() with the same name "%s" has already ' .
        'been previously registered in %s:%d';

    /**
     * @var string[]
     */
    private const ACTIVITY_ATTRIBUTES = [
        ActivityMethod::class,
        MethodRetry::class,
    ];

    /**
     * @param string $class
     * @return array<ActivityPrototype>
     * @throws \ReflectionException
     */
    public function fromClass(string $class): array
    {
        $methods = $this->getActivityPrototypes(new \ReflectionClass($class));

        return \iterator_to_array($methods, false);
    }

    /**
     * @param \ReflectionClass $class
     * @return \Traversable<ActivityPrototype>
     * @throws \ReflectionException
     */
    public function getActivityPrototypes(\ReflectionClass $class): \Traversable
    {
        $reader = $this->getRecursiveReader($class, ActivityInterface::class);

        $createActivityMethodName = \Closure::fromCallable([$this, 'createActivityMethodName']);

        foreach ($class->getMethods() as $method) {
            if (! $this->isValidActivityMethod($method)) {
                continue;
            }

            $reducer = new OptionsReducer(
                $reader->bypass($method, MethodRetry::class)
            );

            $methods = $reader->bypassThrough($method, ActivityMethod::class, $createActivityMethodName);

            foreach ($methods as $data) {
                $current = $reducer->current();

                if ($data !== null) {
                    [$name, $isInterfaced] = $data;
                    $prototype = new ActivityPrototype($name, $method, $class, $isInterfaced);

                    if ($current !== null) {
                        $prototype->setMethodRetry($current);
                    }

                    yield $prototype;
                }

                $reducer->next();
            }
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @return bool
     */
    #[Pure]
    private function isValidActivityMethod(\ReflectionMethod $method): bool
    {
        return ! $method->isStatic() && $method->isPublic();
    }

    /**
     * @param ActivityMethod|null $method
     * @param \ReflectionMethod $reflection
     * @param ActivityInterface|null $interface
     * @param bool $root
     * @return array|null
     */
    private function createActivityMethodName(
        ?ActivityMethod $method,
        \ReflectionMethod $reflection,
        ?ActivityInterface $interface,
        bool $root
    ): ?array {
        $isInterfaced = $interface !== null;
        $prefix = $isInterfaced ? $interface->prefix : '';

        if ($method !== null) {
            return [$prefix . ($method->name ?? $reflection->getName()), $isInterfaced];
        }

        if ($root) {
            return [$prefix . $reflection->getName(), $isInterfaced];
        }

        return null;
    }
}
