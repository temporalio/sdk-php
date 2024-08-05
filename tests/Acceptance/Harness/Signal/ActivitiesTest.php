<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\Activities;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

const ACTIVITY_COUNT = 5;
const ACTIVITY_RESULT = 6;

class ActivitiesTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Signal_Activities')]WorkflowStubInterface $stub,
    ): void {
        $stub->signal('mySignal');
        self::assertSame(ACTIVITY_COUNT * ACTIVITY_RESULT, $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $total = 0;

    #[WorkflowMethod('Harness_Signal_Activities')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->total > 0);
        return $this->total;
    }

    #[SignalMethod('mySignal')]
    public function mySignal()
    {
        $promises = [];
        for ($i = 0; $i < ACTIVITY_COUNT; ++$i) {
            $promises[] = Workflow::executeActivity(
                'result',
                options: ActivityOptions::new()->withStartToCloseTimeout(10)
            );
        }

        yield Promise::all($promises)
            ->then(fn(array $results) => $this->total = \array_sum($results));
    }
}

#[ActivityInterface]
class FeatureActivity
{
    #[ActivityMethod('result')]
    public function result(): int
    {
        return ACTIVITY_RESULT;
    }
}
