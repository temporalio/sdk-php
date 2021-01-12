<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Worker\Transport\RpcConnectionInterface;

/**
 * @template-covariant T of object
 */
final class WorkflowProxy extends Proxy
{
    /**
     * @var string
     */
    private const ERROR_UNDEFINED_WORKFLOW_METHOD =
        'The given stub class "%s" does not contain a workflow method named "%s"'
    ;

    /**
     * @var string
     */
    private const ERROR_UNDEFINED_METHOD =
        'The given stub class "%s" does not contain a workflow, query or signal method named "%s"'
    ;

    /**
     * @var WorkflowStubInterface|null
     */
    private ?WorkflowStubInterface $stub = null;

    /**
     * @var WorkflowPrototype|null
     */
    private ?WorkflowPrototype $prototype = null;

    /**
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var WorkflowPrototype[]
     */
    private array $workflows;

    /**
     * @var WorkflowOptions
     */
    private WorkflowOptions $options;

    /**
     * @var string
     */
    private string $class;

    /**
     * @param RpcConnectionInterface $rpc
     * @param MarshallerInterface $marshaller
     * @param string $class
     * @param array<WorkflowPrototype> $workflows
     * @param WorkflowOptions $options
     */
    public function __construct(
        RpcConnectionInterface $rpc,
        MarshallerInterface $marshaller,
        string $class,
        array $workflows,
        WorkflowOptions $options
    ) {
        $this->rpc = $rpc;
        $this->class = $class;
        $this->marshaller = $marshaller;
        $this->workflows = $workflows;
        $this->options = $options;
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed|void
     */
    public function __call(string $method, array $args)
    {
        // If the proxy does not contain information about the running workflow,
        // then we try to create a new stub from the workflow method and start
        // the workflow.
        if (! $this->isRunning()) {
            $this->prototype = $this->findPrototypeByHandlerNameOrFail($method);

            // Merge options with defaults defined using attributes:
            //  - #[MethodRetry]
            //  - #[CronSchedule]
            $options = $this->options->mergeWith(
                $this->prototype->getMethodRetry(),
                $this->prototype->getCronSchedule()
            );

            $this->stub = new WorkflowStub($this->rpc, $this->marshaller, $this->prototype->getID(), $options);

            return $this->stub->start(...$args);
        }

        // Otherwise, we try to find a suitable workflow "query" method.
        foreach ($this->prototype->getQueryHandlers() as $name => $query) {
            if ($query->getName() === $method) {
                return $this->stub->query($name, $args);
            }
        }

        // Otherwise, we try to find a suitable workflow "signal" method.
        foreach ($this->prototype->getSignalHandlers() as $name => $signal) {
            if ($signal->getName() === $method) {
                $this->stub->signal($name, $args);

                return;
            }
        }

        throw new \BadMethodCallException(
            \sprintf(self::ERROR_UNDEFINED_METHOD, $this->class, $method)
        );
    }

    /**
     * @return bool
     */
    private function isRunning(): bool
    {
        return $this->stub !== null && $this->prototype !== null;
    }

    /**
     * @param string $name
     * @return WorkflowPrototype
     */
    private function findPrototypeByHandlerNameOrFail(string $name): WorkflowPrototype
    {
        $prototype = $this->findPrototypeByHandlerName($this->workflows, $name);

        if ($prototype === null) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_WORKFLOW_METHOD, $this->class, $name)
            );
        }

        return $prototype;
    }
}
