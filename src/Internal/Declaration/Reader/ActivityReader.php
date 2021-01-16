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
        'but %s::%s() does not meet these criteria'
    ;

    /**
     * @var string
     */
    private const ERROR_DECLARATION_DUPLICATION =
        'An Activity method %s::%s() with the same name "%s" has already ' .
        'been previously registered in %s:%d'
    ;

    /**
     * @param string $class
     * @return ActivityPrototype[]
     * @throws \ReflectionException
     */
    public function fromClass(string $class): array
    {
        $reflection = new \ReflectionClass($class);

        return $this->fromReflectionClass($reflection);
    }

    /**
     * @param \ReflectionClass $class
     * @param array<string, ActivityPrototype> $result
     * @return array<string, ActivityPrototype>
     */
    private function fromReflectionClass(\ReflectionClass $class, array $result = []): array
    {
        //
        // Read #[ActivityInterface] attribute or null.
        //
        $interface = $this->findActivityInterface($class);

        //
        // Then we should try to search any public activity methods.
        //
        foreach ($class->getMethods() as $method) {
            $attribute = $this->resolveValidActivityMethod($class, $method);

            // Skip registration of all non-valid activity methods.
            if ($attribute === null) {
                continue;
            }

            $name = $this->createActivityName($method, $attribute, $interface);

            //
            // We should check the existence of a method with the same name
            // and, if it is duplicated, throw an exception with the
            // appropriate message.
            //
            if (isset($result[$name])) {
                $previous = $result[$name];

                $handler = $previous->getHandler();
                $message = \vsprintf(self::ERROR_DECLARATION_DUPLICATION, [
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $name,
                    $handler->getFileName(),
                    $handler->getStartLine()
                ]);

                throw new \LogicException($message);
            }

            $result[$name] = new ActivityPrototype($name, $method, $class, $interface !== null);
        }

        //
        // Then we read the trait methods as our own.
        //
        foreach ($class->getTraits() ?? [] as $trait) {
            $result = $this->fromReflectionClass($trait, $result);
        }

        return \array_values($result);
    }

    /**
     * @param \ReflectionClass $ctx
     * @param \ReflectionMethod $method
     * @return ActivityMethod|null
     */
    private function resolveValidActivityMethod(\ReflectionClass $ctx, \ReflectionMethod $method): ?ActivityMethod
    {
        $isValid = $this->isValidActivityMethod($method);

        /** @var ActivityMethod $attribute */
        $attribute = $this->reader->firstFunctionMetadata($method, ActivityMethod::class);

        //
        // In the case that there is an activity method attribute, but
        // the context (method definition) is not correct, then an
        // exception should be thrown with an appropriate error message.
        //
        if ($attribute !== null && ! $isValid) {
            throw new \LogicException(\vsprintf(self::ERROR_BAD_DECLARATION, [
                $ctx->getName(),
                $method->getName()
            ]));
        }

        if (! $isValid) {
            return null;
        }

        return $attribute ?? new ActivityMethod();
    }

    /**
     * @param \ReflectionMethod $method
     * @return bool
     */
    #[Pure]
    private function isValidActivityMethod(\ReflectionMethod $method): bool
    {
        return !$method->isStatic() && $method->isPublic();
    }

    /**
     * @param \ReflectionFunctionAbstract $reflection
     * @param ActivityMethod $method
     * @param ActivityInterface|null $interface
     * @return string
     */
    #[Pure]
    private function createActivityName(
        \ReflectionFunctionAbstract $reflection,
        ActivityMethod $method,
        ?ActivityInterface $interface
    ): string {
        $result = '';

        if ($interface !== null) {
            $result .= $interface->prefix;
        }

        return $result . ($method->name ?? $reflection->getName());
    }

    /**
     * @param \ReflectionClass $class
     * @return ActivityInterface|null
     */
    private function findActivityInterface(\ReflectionClass $class): ?ActivityInterface
    {
        $attributes = $this->reader->getClassMetadata($class, ActivityInterface::class);

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($attributes as $attribute) {
            return $attribute;
        }

        return null;
    }
}
