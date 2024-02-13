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
use Temporal\DataConverter\Type;
use Temporal\Internal\Client\WorkflowProxy;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Support\Reflection;
use Temporal\Internal\Transport\CompletableResultInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowContextInterface;

final class ChildWorkflowProxy extends Proxy
{
    private const ERROR_UNDEFINED_WORKFLOW_METHOD =
        'The given stub class "%s" does not contain a workflow method named "%s"';

    private const ERROR_UNDEFINED_METHOD =
        'The given stub class "%s" does not contain a workflow or signal method named "%s"';

    private const ERROR_UNSUPPORTED_METHOD =
        'The method named "%s" (%s) cannot be executed from a child workflow stub. ' .
        'Only workflow and signal methods are allowed';

    private ?ChildWorkflowStubInterface $stub = null;

    /**
     * @param class-string $class
     */
    public function __construct(
        private readonly string $class,
        private readonly WorkflowPrototype $workflow,
        private readonly ChildWorkflowOptions $options,
        private readonly WorkflowContextInterface $context,
    ) {
    }

    /**
     * @param non-empty-string $method
     * @param array $args
     * @return CompletableResultInterface
     */
    public function __call(string $method, array $args): PromiseInterface
    {
        // If the proxy does not contain information about the running workflow,
        // then we try to create a new stub from the workflow method and start
        // the workflow.
        if (!$this->isRunning()) {
            $handler = $this->workflow->getHandler();

            if ($method !== $handler?->getName()) {
                throw new \BadMethodCallException(
                    \sprintf(self::ERROR_UNDEFINED_WORKFLOW_METHOD, $this->class, $method)
                );
            }

            // Merge options with defaults defined using attributes:
            //  - #[MethodRetry]
            //  - #[CronSchedule]
            $options = $this->options->mergeWith(
                $this->workflow->getMethodRetry(),
                $this->workflow->getCronSchedule()
            );

            $this->stub = $this->context->newUntypedChildWorkflowStub(
                $this->workflow->getID(),
                $options,
            );

            if ($handler !== null) {
                $args = Reflection::orderArguments($handler, $args);
            }

            return $this->stub->execute($args, $this->resolveReturnType($this->workflow));
        }

        // Otherwise, we try to find a suitable workflow "signal" method.
        foreach ($this->workflow->getSignalHandlers() as $name => $signal) {
            if ($signal->getName() === $method) {
                $args = Reflection::orderArguments($signal, $args);

                return $this->stub->signal($name, $args);
            }
        }

        // Otherwise, we try to find a suitable workflow "query" method.
        foreach ($this->workflow->getQueryHandlers() as $name => $query) {
            if ($query->getName() === $method) {
                throw new \BadMethodCallException(
                    \sprintf(self::ERROR_UNSUPPORTED_METHOD, $method, $name)
                );
            }
        }

        throw new \BadMethodCallException(
            \sprintf(self::ERROR_UNDEFINED_METHOD, $this->class, $method)
        );
    }

    /**
     * @param WorkflowPrototype $prototype
     * @return Type|null
     */
    private function resolveReturnType(WorkflowPrototype $prototype): ?Type
    {
        if ($attribute = $prototype->getReturnType()) {
            return Type::create($attribute);
        }

        $handler = $prototype->getHandler();

        return Type::create($handler?->getReturnType());
    }

    /**
     * @psalm-assert-if-true ChildWorkflowStubInterface $this->stub
     * @psalm-assert-if-false null $this->stub
     */
    private function isRunning(): bool
    {
        return $this->stub !== null;
    }
}
