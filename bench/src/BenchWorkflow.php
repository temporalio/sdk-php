<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class BenchWorkflow
{
    #[WorkflowMethod(name: 'BenchWorkflow')]
    public function run(int $activities, int $payloadBytes)
    {
        $activity = Workflow::newActivityStub(
            BenchActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );
        $payload = \str_repeat('x', $payloadBytes);

        for ($i = 0; $i < $activities; $i++) {
            yield $activity->echo($payload);
        }

        return $activities;
    }
}
