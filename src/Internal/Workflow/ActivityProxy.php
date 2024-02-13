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
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteLocalActivityInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Support\Reflection;
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
     * @var ActivityOptionsInterface
     */
    private ActivityOptionsInterface $options;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $ctx;

    /**
     * @param string $class
     * @param array<ActivityPrototype> $activities
     * @param ActivityOptionsInterface $options
     * @param WorkflowContextInterface $ctx
     * @param Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> $callsInterceptor
     */
    public function __construct(
        string $class,
        array $activities,
        ActivityOptionsInterface $options,
        WorkflowContextInterface $ctx,
        private Pipeline $callsInterceptor,
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
        $options = $this->options->mergeWith($handler->getMethodRetry());

        $args = Reflection::orderArguments($handler->getHandler(), $args);

        return $handler->isLocalActivity()
            // Run local activity through an interceptor pipeline
            ? $this->callsInterceptor->with(
                fn(ExecuteLocalActivityInput $input): PromiseInterface => $this->ctx
                    ->newUntypedActivityStub($input->options)
                    ->execute($input->type, $input->args, $input->returnType, true),
                /** @see WorkflowOutboundCallsInterceptor::executeLocalActivity() */
                'executeLocalActivity',
            )(
                new ExecuteLocalActivityInput(
                    $handler->getID(),
                    $args,
                    $options,
                    $type,
                    $handler->getHandler(),
                )
            )

            // Run activity through an interceptor pipeline
            : $this->callsInterceptor->with(
                fn(ExecuteActivityInput $input): PromiseInterface => $this->ctx
                    ->newUntypedActivityStub($input->options)
                    ->execute($input->type, $input->args, $input->returnType),
                /** @see WorkflowOutboundCallsInterceptor::executeActivity() */
                'executeActivity',
            )(
                new ExecuteActivityInput(
                    $handler->getID(),
                    $args,
                    $options,
                    $type,
                    $handler->getHandler(),
                )
            );
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
