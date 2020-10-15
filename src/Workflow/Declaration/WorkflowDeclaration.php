<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Declaration;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Worker\Declaration\HandledDeclaration;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

final class WorkflowDeclaration extends HandledDeclaration implements WorkflowDeclarationInterface
{
    /**
     * @var array|\Closure[]
     */
    private array $queryHandlers = [];

    /**
     * @var array|\Closure[]
     */
    private array $signalHandlers = [];

    /**
     * @param object $object
     * @param ReaderInterface $reader
     * @return WorkflowDeclarationInterface[]
     */
    public static function fromObject(object $object, ReaderInterface $reader): iterable
    {
        $signalHandlers = $queryHandlers = [];

        $reflection = new \ReflectionObject($object);

        /** @var \ReflectionMethod $method */
        foreach (self::eachMethod($reader, $reflection, QueryMethod::class) as $method => $meta) {
            $name = $meta->name ?? self::createQueryHandlerName($reflection, $method);

            $queryHandlers[$name] = $method->getClosure($object);
        }

        /** @var \ReflectionMethod $method */
        foreach (self::eachMethod($reader, $reflection, SignalMethod::class) as $method => $meta) {
            $name = $meta->name ?? self::createSignalHandlerName($reflection, $method);

            $signalHandlers[$name] = $method->getClosure($object);
        }

        /** @var \ReflectionMethod $method */
        foreach (self::eachMethod($reader, $reflection, WorkflowMethod::class) as $method => $meta) {
            $name = $meta->name ?? self::createWorkflowName($reflection, $method);

            $workflow = new self($name, $method->getClosure($object));

            foreach ($queryHandlers as $name => $callback) {
                $workflow->addQueryHandler($name, $callback);
            }

            foreach ($signalHandlers as $name => $callback) {
                $workflow->addSignalHandler($name, $callback);
            }

            yield $workflow;
        }
    }

    /**
     * @param ReaderInterface $reader
     * @param \ReflectionClass $ctx
     * @param string $attribute
     * @return iterable
     */
    private static function eachMethod(ReaderInterface $reader, \ReflectionClass $ctx, string $attribute): iterable
    {
        foreach ($ctx->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($reader->getMethodMetadata($method, $attribute) as $meta) {
                yield $method => $meta;
            }
        }
    }

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @return string
     */
    private static function createWorkflowName(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        return self::createDefaultName($class, $method);
    }

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @return string
     */
    private static function createDefaultName(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        return $class->getName() . '::' . $method->getName();
    }

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @return string
     */
    private static function createQueryHandlerName(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        return self::createDefaultName($class, $method);
    }

    /**
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @return string
     */
    private static function createSignalHandlerName(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        return self::createDefaultName($class, $method);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryHandlers(): array
    {
        return $this->queryHandlers;
    }

    /**
     * {@inheritDoc}
     */
    public function addQueryHandler(string $name, callable $callback): void
    {
        // TODO Add exists assertion
        $this->queryHandlers[$name] = \Closure::fromCallable($callback);
    }

    /**
     * {@inheritDoc}
     */
    public function getSignalHandlers(): array
    {
        return $this->signalHandlers;
    }

    /**
     * {@inheritDoc}
     */
    public function addSignalHandler(string $name, callable $callback): void
    {
        // TODO Add exists assertion
        $this->signalHandlers[$name] = \Closure::fromCallable($callback);
    }
}
