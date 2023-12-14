<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Support\Reflection;
use Temporal\Internal\Workflow\Proxy;
use Temporal\Workflow\ReturnType;

/**
 * @template-covariant T of object
 */
final class WorkflowProxy extends Proxy
{
    private const ERROR_UNDEFINED_METHOD =
        'The given workflow class "%s" does not contain a workflow, query or signal method named "%s"';

    /**
     * @param WorkflowClient $client
     * @param WorkflowStubInterface $stub
     * @param WorkflowPrototype $prototype
     */
    public function __construct(
        public WorkflowClient $client,
        private readonly WorkflowStubInterface $stub,
        private readonly WorkflowPrototype $prototype,
    ) {
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed|void
     */
    public function __call(string $method, array $args)
    {
        if ($method === $this->prototype->getHandler()?->getName()) {
            $args = Reflection::orderArguments($this->prototype->getHandler(), $args);

            // no timeout (use async mode to get it)
            return $this->client->start($this, ...$args)->getResult($this->__getReturnType());
        }

        // Otherwise, we try to find a suitable workflow "query" method.
        foreach ($this->prototype->getQueryHandlers() as $name => $query) {
            if ($query->getName() === $method) {
                $args = Reflection::orderArguments($query, $args);

                $result = $this->stub->query($name, ...$args);
                if ($result === null) {
                    return null;
                }

                return $result->getValue(0, $query->getReturnType());
            }
        }

        // Otherwise, we try to find a suitable workflow "signal" method.
        foreach ($this->prototype->getSignalHandlers() as $name => $signal) {
            if ($signal->getName() === $method) {
                $args = Reflection::orderArguments($signal, $args);

                $this->stub->signal($name, ...$args);

                return;
            }
        }

        $class = $this->prototype->getClass();

        throw new \BadMethodCallException(
            \sprintf(self::ERROR_UNDEFINED_METHOD, $class->getName(), $method)
        );
    }

    /**
     * TODO rename: Method names cannot use underscore (PSR conflict)
     *
     * @return WorkflowStubInterface
     * @internal
     */
    public function __getUntypedStub(): WorkflowStubInterface
    {
        return $this->stub;
    }

    /**
     * TODO rename: Method names cannot use underscore (PSR conflict)
     *
     * @return ReturnType|null
     * @internal
     */
    public function __getReturnType(): ?ReturnType
    {
        return $this->prototype->getReturnType();
    }

    /**
     * @return bool
     * @internal
     */
    public function hasHandler(): bool
    {
        return $this->getHandlerReflection() !== null;
    }

    /**
     * @return \ReflectionMethod
     * @internal
     */
    public function getHandlerReflection(): \ReflectionMethod
    {
        return $this->prototype->getHandler() ?? throw new \LogicException(
            'The workflow does not contain a handler method.'
        );
    }

    /**
     * @param non-empty-string $name Signal name
     * @return \ReflectionFunctionAbstract|null
     */
    public function findSignalReflection(string $name): ?\ReflectionFunctionAbstract
    {
        return $this->prototype->getSignalHandlers()[$name] ?? null;
    }
}
