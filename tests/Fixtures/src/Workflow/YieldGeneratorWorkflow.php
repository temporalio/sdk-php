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
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

use function React\Promise\resolve;

#[Workflow\WorkflowInterface]
class YieldGeneratorWorkflow
{
    #[WorkflowMethod(name: 'YieldGeneratorWorkflow')]
    public function handler(): iterable {
        return yield $this->generate();
    }

    private function generate(): \Generator
    {
        yield resolve(true);
        yield resolve(false);
        yield resolve(null);
        yield 'foo';
        yield Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout(5),
        )->empty();
        yield Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout(5),
        )->lower('Hello World!');
        return 'bar';
    }
}
