<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Temporal\Activity\ActivityOptions;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class BenchParallelWorkflow
{
    #[WorkflowMethod(name: 'BenchParallelWorkflow')]
    public function run(int $activities, int $payloadBytes)
    {
        $activity = Workflow::newActivityStub(
            BenchActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );
        $payload = \str_repeat('x', $payloadBytes);

        $promises = [];
        for ($i = 0; $i < $activities; $i++) {
            $promises[] = $activity->echo($payload);
        }
        yield Promise::all($promises);

        return $activities;
    }
}
