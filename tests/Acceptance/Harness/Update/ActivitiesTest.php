<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Activities;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
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
    public function run(): int
    {
        Workflow::await(fn(): bool => $this->total > 0);
        return $this->total;
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate(): int
    {
        $scopes = [];
        for ($i = 0; $i < ACTIVITY_COUNT; ++$i) {
            $scopes[] = Workflow::async(
                static fn() => Workflow::executeActivity(
                    'result',
                    options: ActivityOptions::new()->withStartToCloseTimeout(10)
                ),
            );
        }

        return $this->total = \array_sum(Workflow::all($scopes));
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
