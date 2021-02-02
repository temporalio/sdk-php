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
    public function run()
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(60)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1))
        );

        $saga = new Workflow\Saga();
        $saga->setParallelCompensation(true);

        try {
            yield $simple->echo('test');
            $saga->addCompensation(
                function () use ($simple) {
                    yield $simple->slow('compensate echo');
                }
            );

            yield $simple->lower('TEST');
            $saga->addCompensation(
                function () use ($simple) {
                    yield $simple->prefix('prefix', 'COMPENSATE LOWER');
                }
            );

            yield $simple->fail();
        } catch (\Throwable $e) {
            yield $saga->compensate();
            throw $e;
        }
    }
}
