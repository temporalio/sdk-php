<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class BenchCpuWorkflow
{
    #[WorkflowMethod(name: 'BenchCpuWorkflow')]
    public function run(int $activities, int $burnMilliseconds)
    {
        $activity = Workflow::newActivityStub(
            BenchActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        self::burn($burnMilliseconds);
        for ($i = 0; $i < $activities; $i++) {
            yield $activity->echo('x');
            self::burn($burnMilliseconds);
        }

        return $activities;
    }

    private static function burn(int $milliseconds): void
    {
        $until = \hrtime(true) + $milliseconds * 1_000_000;
        while (\hrtime(true) < $until) {
        }
    }
}
