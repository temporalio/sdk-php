<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class BenchKvWorkflow
{
    #[WorkflowMethod(name: 'BenchKvWorkflow')]
    public function run(int $activities, int $operations)
    {
        $activity = Workflow::newActivityStub(
            BenchActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        for ($i = 0; $i < $activities; $i++) {
            yield $activity->kv($operations);
        }

        return $activities;
    }
}
