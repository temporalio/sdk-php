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
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\LocalActivityOptions;
use Temporal\Worker\FeatureFlags;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteLocalActivityInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Support\OptionsMerger;
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
     * @param array<ActivityPrototype> $activities
     * @param Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> $callsInterceptor
     */
    public function __construct(
        private readonly string $class,
        private readonly array $activities,
        private readonly ActivityOptions|LocalActivityOptions $options,
        private readonly WorkflowContextInterface $ctx,
        private readonly Pipeline $callsInterceptor,
    ) {}

    /**
     * @return CompletableResultInterface
     */
    public function __call(string $method, array $args = []): PromiseInterface
    {
        $prototype = $this->findPrototypeByHandlerNameOrFail($method);
        $type = $prototype->getHandler()->getReturnType();

        if (FeatureFlags::$warnOnActivityMethodWithoutAttribute && !$prototype->getHandler()->getAttributes(ActivityMethod::class, \ReflectionAttribute::IS_INSTANCEOF)) {
            \trigger_error(
                \sprintf(
                    'Using implicit activity methods is deprecated. Explicitly mark activity method %s with #[%s] attribute instead.',
                    $prototype->getHandler()->getDeclaringClass()->getName() . '::' . $method,
                    ActivityMethod::class,
                ),
                \E_USER_DEPRECATED,
            );
        }

        $options = $prototype->isLocalActivity()
            ? LocalActivityOptions::new()
            : ActivityOptions::new();
        $options = OptionsMerger::merge($options, $prototype->getMethodOptions());
        $options = $options->mergeWith($prototype->getMethodRetry());
        $options = OptionsMerger::merge($options, $this->options);

        $args = Reflection::orderArguments($prototype->getHandler(), $args);

        if ($prototype->isLocalActivity()) {
            if (!$options instanceof LocalActivityOptions) {
                throw new \LogicException(\sprintf(
                    'Local activity options must be an instance of "%s", "%s" given',
                    LocalActivityOptions::class,
                    \get_class($options),
                ));
            }
            return $this->callsInterceptor->with(
                fn(ExecuteLocalActivityInput $input): PromiseInterface => $this->ctx
                    ->newUntypedActivityStub($input->options)
                    ->execute($input->type, $input->args, $input->returnType, true),
                /** @see WorkflowOutboundCallsInterceptor::executeLocalActivity() */
                'executeLocalActivity',
            )(new ExecuteLocalActivityInput(
                $prototype->getID(),
                $args,
                $options,
                $type,
                $prototype->getHandler(),
            ));
        }

        if (!$options instanceof ActivityOptions) {
            throw new \LogicException(\sprintf(
                'Activity options must be an instance of "%s", "%s" given',
                ActivityOptions::class,
                \get_class($options),
            ));
        }
        return $this->callsInterceptor->with(
            fn(ExecuteActivityInput $input): PromiseInterface => $this->ctx
                ->newUntypedActivityStub($input->options)
                ->execute($input->type, $input->args, $input->returnType),
            /** @see WorkflowOutboundCallsInterceptor::executeActivity() */
            'executeActivity',
        )(new ExecuteActivityInput(
            $prototype->getID(),
            $args,
            $options,
            $type,
            $prototype->getHandler(),
        ));

    }

    private function findPrototypeByHandlerNameOrFail(string $name): ActivityPrototype
    {
        $prototype = $this->findPrototypeByHandlerName($this->activities, $name);

        if ($prototype === null) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_ACTIVITY_METHOD, $this->class, $name),
            );
        }

        return $prototype;
    }
}
