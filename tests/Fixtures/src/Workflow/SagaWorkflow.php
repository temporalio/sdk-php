<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;

#[Workflow\WorkflowInterface]
class SagaWorkflow
{
    #[Workflow\WorkflowMethod(name: 'SagaWorkflow')]
    public function run(): void
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(60)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        $saga = new Workflow\Saga();
        $saga->setParallelCompensation(true);

        try {
            $simple->echo('test');
            $saga->addCompensation(
                static fn() => $simple->slow('compensate echo'),
            );

            $simple->lower('TEST');
            $saga->addCompensation(
                static fn() => $simple->prefix('prefix', 'COMPENSATE LOWER'),
            );

            $simple->fail();
        } catch (\Throwable $e) {
            $saga->compensate()->await();
            throw $e;
        }
    }
}
