<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @template-extends Reader<WorkflowPrototype>
 */
class WorkflowReader extends Reader
{
    /**
     * @var string
     */
    private const ERROR_HANDLER_NOT_FOUND =
        'Can not find workflow handler, because class %s has no method marked with #[%s] attribute'
    ;

    /**
     * @param string $class
     * @return WorkflowPrototype
     * @throws \ReflectionException
     */
    public function fromClass(string $class): WorkflowPrototype
    {
        $reflection = new \ReflectionClass($class);

        // Find #[WorkflowMethod] and create WorkflowPrototype or null
        $prototype = $this->findWorkflowHandler($reflection, $this->findWorkflowInterface($reflection));

        if ($prototype === null) {
            $message = \sprintf(self::ERROR_HANDLER_NOT_FOUND, $class, WorkflowMethod::class);
            throw new \InvalidArgumentException($message);
        }

        /**
         * Add signals
         *
         * @var SignalMethod $signal
         */
        foreach ($this->annotatedMethods($reflection, SignalMethod::class) as $signal => $handler) {
            $name = $signal->name ?? $handler->getName();

            $prototype->addSignalHandler($name, $handler);
        }

        /**
         * Add queries
         *
         * @var QueryMethod $query
         */
        foreach ($this->annotatedMethods($reflection, QueryMethod::class) as $query => $handler) {
            $name = $query->name ?? $handler->getName();

            $prototype->addQueryHandler($name, $handler);
        }

        return $prototype;
    }

    /**
     * @param \ReflectionClass $reflection
     * @param WorkflowInterface|null $interface
     * @return WorkflowPrototype|null
     */
    private function findWorkflowHandler(\ReflectionClass $reflection, ?WorkflowInterface $interface): ?WorkflowPrototype
    {
        /**
         * @var WorkflowMethod $method
         * @var \ReflectionFunction $handler
         * @noinspection LoopWhichDoesNotLoopInspection
         */
        foreach ($this->annotatedMethods($reflection, WorkflowMethod::class) as $method => $handler) {
            $name = $method->name ?? $handler->getName();

            $prototype = new WorkflowPrototype($name, $handler, $reflection, $interface !== null);

            if ($cron = $this->findAttribute($handler, CronSchedule::class)) {
                $prototype->setCronSchedule($cron);
            }

            if ($retry = $this->findAttribute($handler, MethodRetry::class)) {
                $prototype->setMethodRetry($retry);
            }

            return $prototype;
        }

        return null;
    }

    /**
     * @param \ReflectionClass $class
     * @return WorkflowInterface|null
     */
    private function findWorkflowInterface(\ReflectionClass $class): ?WorkflowInterface
    {
        $attributes = $this->reader->getClassMetadata($class, WorkflowInterface::class);

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($attributes as $attribute) {
            return $attribute;
        }

        return null;
    }
}
