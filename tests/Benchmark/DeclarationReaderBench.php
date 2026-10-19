<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Tests\Workflow\SimpleWorkflow;

#[BeforeMethods('setUp')]
#[Revs(1000)]
#[Iterations(10)]
#[Warmup(1)]
final class DeclarationReaderBench
{
    private ActivityReader $activities;
    private WorkflowReader $workflows;

    public function setUp(): void
    {
        $reader = new AttributeReader();
        $this->activities = new ActivityReader($reader);
        $this->workflows = new WorkflowReader($reader);
    }

    public function benchActivityClass(): void
    {
        $this->activities->fromClass(SimpleActivity::class);
    }

    public function benchWorkflowClass(): void
    {
        $this->workflows->fromClass(SimpleWorkflow::class);
    }
}
