<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Temporal\Activity\ActivityOptions;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class BenchIoWorkflow
{
    #[WorkflowMethod(name: 'BenchIoWorkflow')]
    public function run(int $activities, int $milliseconds)
    {
        $activity = Workflow::newActivityStub(
            BenchActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );
        $promises = [];
        for ($i = 0; $i < $activities; $i++) {
            $promises[] = $activity->io($milliseconds);
        }
        yield Promise::all($promises);

        return $activities;
    }
}
