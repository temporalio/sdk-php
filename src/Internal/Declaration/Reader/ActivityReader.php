<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Activity\ActivityId;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Activity\ActivityPriority;
use Temporal\Activity\CancellationType;
use Temporal\Activity\HeartbeatTimeout;
use Temporal\Activity\RetryPolicy;
use Temporal\Activity\ScheduleToCloseTimeout;
use Temporal\Activity\ScheduleToStartTimeout;
use Temporal\Activity\StartToCloseTimeout;
use Temporal\Activity\Summary;
use Temporal\Activity\TaskQueue;
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
     * @param class-string $class
     * @return array<ActivityPrototype>
     * @throws \ReflectionException
     */
    public function fromClass(string $class): array
    {
        return $this->getActivityPrototypes(new \ReflectionClass($class));
    }

    /**
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
     * @return array<ActivityPrototype>
     * @throws \ReflectionException
     */
    private function getMethodGroups(ClassNode $graph, \ReflectionMethod $root): array
    {
        $previousRetry = null;
        $previousOptions = null;

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
            $contextualOptions = $previousOptions;

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

                $options = $this->reader->firstFunctionMetadata($method, ActivityOptions::class);
                $options = $this->mergeGranularAttributes($method, $options);
                $classOptions = $this->reader->firstClassMetadata($ctx->getReflection(), ActivityOptions::class);
                $classOptions = $this->mergeGranularAttributes($ctx->getReflection(), $classOptions);

                if ($classOptions !== null) {
                    $options = $classOptions->mergeWithOptions($options);
                }

                if ($previousOptions instanceof ActivityOptionsInterface) {
                    $options = $options === null
                        ? $previousOptions
                        : $previousOptions->mergeWithOptions($options);
                }

                if ($options !== null) {
                    $contextualOptions = $contextualOptions ? $options->mergeWithOptions($contextualOptions) : $options;
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
                            \sprintf(self::ERROR_BAD_DECLARATION, $reflection->getName(), $method->getName()),
                        );
                    }

                    continue;
                }

                if ($attribute === null && $this->isMagic($method)) {
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

                if ($options !== null) {
                    $prototype->setMethodOptions($options);
                }

                $prototypes[$name] = $prototype;
            }

            $previousRetry = $contextualRetry;
            $previousOptions = $contextualOptions;
        }

        return $prototypes;
    }

    private function activityName(
        \ReflectionMethod $ref,
        ActivityInterface $int,
        ?ActivityMethod $method,
    ): string {
        return $method === null
            ? $int->prefix . $ref->getName()
            : $int->prefix . ($method->name ?? $ref->getName());
    }

    private function assertActivityNotExists(
        string $name,
        array $activities,
        \ReflectionClass $class,
        \ReflectionMethod $method,
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

    private function mergeGranularAttributes(
        \ReflectionMethod|\ReflectionClass $reflection,
        ?ActivityOptions $options,
    ): ?ActivityOptions {
        $taskQueue = $this->firstMetadata($reflection, TaskQueue::class);
        if ($taskQueue !== null) {
            $options = ($options ?? ActivityOptions::new())->withTaskQueue($taskQueue->name);
        }

        $scheduleToClose = $this->firstMetadata($reflection, ScheduleToCloseTimeout::class);
        if ($scheduleToClose !== null) {
            $options = ($options ?? ActivityOptions::new())->withScheduleToCloseTimeout($scheduleToClose->interval);
        }

        $scheduleToStart = $this->firstMetadata($reflection, ScheduleToStartTimeout::class);
        if ($scheduleToStart !== null) {
            $options = ($options ?? ActivityOptions::new())->withScheduleToStartTimeout($scheduleToStart->interval);
        }

        $startToClose = $this->firstMetadata($reflection, StartToCloseTimeout::class);
        if ($startToClose !== null) {
            $options = ($options ?? ActivityOptions::new())->withStartToCloseTimeout($startToClose->interval);
        }

        $heartbeat = $this->firstMetadata($reflection, HeartbeatTimeout::class);
        if ($heartbeat !== null) {
            $options = ($options ?? ActivityOptions::new())->withHeartbeatTimeout($heartbeat->interval);
        }

        $cancellationType = $this->firstMetadata($reflection, CancellationType::class);
        if ($cancellationType !== null) {
            $options = ($options ?? ActivityOptions::new())->withCancellationType($cancellationType->value);
        }

        $activityId = $this->firstMetadata($reflection, ActivityId::class);
        if ($activityId !== null) {
            $options = ($options ?? ActivityOptions::new())->withActivityId($activityId->id);
        }

        $retryPolicy = $this->firstMetadata($reflection, RetryPolicy::class);
        if ($retryPolicy !== null) {
            $options = ($options ?? ActivityOptions::new())->withRetryOptions($retryPolicy->options);
        }

        $priority = $this->firstMetadata($reflection, ActivityPriority::class);
        if ($priority !== null) {
            $options = ($options ?? ActivityOptions::new())->withPriority($priority->priority);
        }

        $summary = $this->firstMetadata($reflection, Summary::class);
        if ($summary !== null) {
            $options = ($options ?? ActivityOptions::new())->withSummary($summary->text);
        }

        return $options;
    }
}
