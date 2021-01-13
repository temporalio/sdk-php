<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Transport\CompletableResultInterface;
use Temporal\Workflow\ActivityStubInterface;

final class ActivityProxy extends Proxy
{
    /**
     * @var string
     */
    private const ERROR_UNDEFINED_ACTIVITY_METHOD =
        'The given stub class "%s" does not contain an activity method named "%s"'
    ;

    /**
     * @var array<ActivityPrototype>
     */
    private array $activities;

    /**
     * @var ActivityStubInterface
     */
    private ActivityStubInterface $stub;

    /**
     * @var string
     */
    private string $class;

    /**
     * @param string $class
     * @param array<ActivityPrototype> $activities
     * @param ActivityStubInterface $stub
     */
    public function __construct(string $class, array $activities, ActivityStubInterface $stub)
    {
        $this->activities = $activities;
        $this->class = $class;
        $this->stub = $stub;
    }

    /**
     * @param string $method
     * @param array $args
     * @return CompletableResultInterface
     */
    public function __call(string $method, array $args = []): PromiseInterface
    {
        $handler = $this->findPrototypeByHandlerNameOrFail($method);

        return $this->stub->execute($handler->getID(), $args, $handler->getHandler()->getReturnType());
    }

    /**
     * @param string $name
     * @return ActivityPrototype
     */
    private function findPrototypeByHandlerNameOrFail(string $name): ActivityPrototype
    {
        $prototype = $this->findPrototypeByHandlerName($this->activities, $name);

        if ($prototype === null) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_ACTIVITY_METHOD, $this->class, $name)
            );
        }

        return $prototype;
    }
}
