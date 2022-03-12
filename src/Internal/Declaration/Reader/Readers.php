<?php

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Spiral\Attributes\ReaderInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

final class Readers
{
    private WorkflowReader $workflowReader;
    private ActivityReader $activityReader;

    private function __construct(WorkflowReader $workflowReader, ActivityReader $activityReader) {
        $this->workflowReader = $workflowReader;
        $this->activityReader = $activityReader;
    }

    public static function fromReader(ReaderInterface $reader): self
    {
        return new self(new WorkflowReader($reader), new ActivityReader($reader));
    }

    public function activityFromClass(string $class): array
    {
        return $this->activityReader->fromClass($class);
    }

    public function workflowFromClass(string $class): WorkflowPrototype
    {
        return $this->workflowReader->fromClass($class);
    }

    public function workflowFromObject($object): WorkflowPrototype
    {
        return $this->workflowReader->fromObject($object);
    }
}
