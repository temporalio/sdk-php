<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\Graph\ClassNode;
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
     * @param string $class
     * @return array<ActivityPrototype>
     * @throws \ReflectionException
     */
    public function fromClass(string $class): array
    {
        return $this->getActivityPrototypes(new \ReflectionClass($class));
    }

    /**
     * @param \ReflectionClass $class
     * @return array<ActivityPrototype>
     * @throws \ReflectionException
     */
    protected function getActivityPrototypes(\ReflectionClass $class): array
    {
        $ctx = new ClassNode($class);
        $prototypes = [];

        foreach ($class->getMethods() as $reflection) {
            foreach ($this->getMethodGroups($ctx, $reflection) as $name => $prototype) {
                $this->assertActivityNotExists($name, $prototypes, $class, $reflection);

                $prototypes[$name] = $prototype;
            }
        }

        return \array_values($prototypes);
    }

    /**
     * @param ClassNode $graph
     * @param \ReflectionMethod $root
     * @return array<ActivityPrototype>
     * @throws \ReflectionException
     */
    private function getMethodGroups(ClassNode $graph, \ReflectionMethod $root): array
    {
        $previousRetry = null;

        // Activity prototypes
        $prototypes = [];

        //
        // We begin to read all available methods in the reverse hierarchical
        // order (from internal to external).
        //
        // For Example:
        //  class ChildClass extends ParentClass { ... }
        //
        // Group Result:
        //  - ParentClass::method()
        //  - ChildClass::method()
        //
        foreach ($graph->getMethods($root->getName()) as $group) {
            //
            $contextualRetry = $previousRetry;

            //
            // Each group of methods means one level of hierarchy in the
            // inheritance graph.
            //
            /** @var ClassNode $ctx */
            foreach ($group as $ctx => $method) {
                /** @var MethodRetry $retry */
                $retry = $this->reader->firstFunctionMetadata($method, MethodRetry::class);

                if ($retry !== null) {
                    // Update current retry from previous value
                    if ($previousRetry instanceof MethodRetry) {
                        $retry = $retry->mergeWith($previousRetry);
                    }

                    // Update current context
                    $contextualRetry = $contextualRetry ? $retry->mergeWith($contextualRetry) : $retry;
                }

                //
                // In the future, activity methods are available only in
                // those classes that contain the attribute:
                //
                //  - #[ActivityInterface]
                //  - #[LocalActivityInterface]
                //
                $interface = $this->reader->firstClassMetadata($ctx->getReflection(), ActivityInterface::class);

                if ($interface === null) {
                    continue;
                }

                $attribute = $this->reader->firstFunctionMetadata($method, ActivityMethod::class);

                /** @var \ReflectionMethod $method */
                if (!$this->isValidMethod($method)) {
                    if ($attribute !== null) {
                        $reflection = $method->getDeclaringClass();

                        throw new \LogicException(
                            \sprintf(self::ERROR_BAD_DECLARATION, $reflection->getName(), $method->getName())
                        );
                    }

                    continue;
                }

                //
                // The name of the activity must be generated based on the
                // optional prefix on the #[ActivityInterface] attribute and
                // the method's name which can be redefined
                // using #[ActivityMethod] attribute.
                //
                $name = $this->activityName($method, $interface, $attribute);

                $prototype = new ActivityPrototype($interface, $name, $root, $graph->getReflection());

                if ($retry !== null) {
                    $prototype->setMethodRetry($retry);
                }

                $prototypes[$name] = $prototype;
            }

            $previousRetry = $contextualRetry;
        }

        return $prototypes;
    }

    /**
     * @param \ReflectionMethod $ref
     * @param ActivityInterface $int
     * @param ActivityMethod|null $method
     * @return string
     */
    private function activityName(
        \ReflectionMethod $ref,
        ActivityInterface $int,
        ?ActivityMethod $method
    ): string {
        return $method === null
            ? $int->prefix . $ref->getName()
            : $int->prefix . ($method->name ?? $ref->getName());
    }

    /**
     * @param string $name
     * @param array $activities
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     */
    private function assertActivityNotExists(
        string $name,
        array $activities,
        \ReflectionClass $class,
        \ReflectionMethod $method
    ): void {
        if (!isset($activities[$name])) {
            return;
        }

        /** @var ActivityPrototype $previous */
        $previous = $activities[$name];
        $handler = $previous->getHandler();

        $error = \vsprintf(self::ERROR_DECLARATION_DUPLICATION, [
            $class->getName(),
            $method->getName(),
            $name,
            $handler->getFileName(),
            $handler->getStartLine(),
        ]);

        throw new \LogicException($error);
    }
}
