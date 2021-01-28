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
use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Transport\CompletableResultInterface;
use Temporal\Workflow\WorkflowContextInterface;

final class ActivityProxy extends Proxy
{
    /**
     * @var string
     */
    private const ERROR_UNDEFINED_ACTIVITY_METHOD =
        'The given stub class "%s" does not contain an activity method named "%s"';

    /**
     * @var array<ActivityPrototype>
     */
    private array $activities;

    /**
     * @var string
     */
    private string $class;

    /**
     * @var ActivityOptions
     */
    private ActivityOptions $options;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $ctx;

    /**
     * @param string $class
     * @param array<ActivityPrototype> $activities
     * @param ActivityOptions $options
     * @param WorkflowContextInterface $ctx
     */
    public function __construct(
        string $class,
        array $activities,
        ActivityOptions $options,
        WorkflowContextInterface $ctx
    ) {
        $this->activities = $activities;
        $this->class = $class;
        $this->options = $options;
        $this->ctx = $ctx;
    }

    /**
     * @param string $method
     * @param array $args
     * @return CompletableResultInterface
     */
    public function __call(string $method, array $args = []): PromiseInterface
    {
        $handler = $this->findPrototypeByHandlerNameOrFail($method);

        $type = $handler->getHandler()->getReturnType();

        return $this->ctx->newUntypedActivityStub($this->options->mergeWith($handler->getMethodRetry()))
            ->execute($handler->getID(), $args, $type);
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
