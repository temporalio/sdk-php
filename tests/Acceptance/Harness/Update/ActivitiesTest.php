<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Activities;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Promise;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

const ACTIVITY_COUNT = 5;
const ACTIVITY_RESULT = 6;

class ActivitiesTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Update_Activities')]WorkflowStubInterface $stub,
    ): void {
        $updated = $stub->update('my_update')->getValue(0);
        self::assertSame(ACTIVITY_COUNT * ACTIVITY_RESULT, $updated);
        self::assertSame(ACTIVITY_COUNT * ACTIVITY_RESULT, $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $total = 0;

    #[WorkflowMethod('Harness_Update_Activities')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->total > 0);
        return $this->total;
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate()
    {
        $promises = [];
        for ($i = 0; $i < ACTIVITY_COUNT; ++$i) {
            $promises[] = Workflow::executeActivity(
                'result',
                options: ActivityOptions::new()->withStartToCloseTimeout(10)
            );
        }

        return yield Promise::all($promises)
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
