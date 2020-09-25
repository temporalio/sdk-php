<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Declaration;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Meta\WorkflowMethod;

final class Workflow extends HandledDeclaration implements WorkflowInterface
{
    /**
     * @param object $object
     * @param ReaderInterface $reader
     * @return WorkflowInterface[]
     */
    public static function fromObject(object $object, ReaderInterface $reader): iterable
    {
        $reflection = new \ReflectionObject($object);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            /** @var WorkflowMethod $meta */
            foreach ($reader->getMethodMetadata($method, WorkflowMethod::class) as $meta) {
                $name = $meta->name ?? self::createWorkflowName($reflection, $method);

                yield new self($name, $method->getClosure($object));
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
        return $class->getName() . '::' . $method->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryHandlers(): iterable
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * {@inheritDoc}
     */
    public function getSignalHandlers(): iterable
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
