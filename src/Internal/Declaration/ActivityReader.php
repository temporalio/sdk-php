<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use Temporal\Client\Activity\Meta\ActivityInterface;
use Temporal\Client\Activity\Meta\ActivityMethod;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

/**
 * @template-implements ReaderInterface<ActivityDeclarationInterface>
 */
class ActivityReader extends Reader
{
    /**
     * @param string $class
     * @return ActivityDeclarationInterface[]
     * @throws \ReflectionException
     */
    public function fromClass(string $class): iterable
    {
        $reflection = new \ReflectionClass($class);
        $interface = $this->getActivityInterface($reflection);

        foreach ($this->annotatedMethods($reflection, ActivityMethod::class) as $method => $handler) {
            $method->name ??= $handler->getName();

            yield new ActivityDeclaration($interface, $method, $handler);
        }
    }

    /**
     * @param \ReflectionClass $class
     * @return ActivityInterface
     */
    private function getActivityInterface(\ReflectionClass $class): ActivityInterface
    {
        $attributes = $this->reader->getClassMetadata($class, ActivityInterface::class);

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($attributes as $attribute) {
            return $attribute;
        }

        return new ActivityInterface();
    }
}
