<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityMethod;

use Temporal\Activity;
use Temporal\Activity\ActivityMethod;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Testing\DeprecationCollector;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ActivityMethodTest extends TestCase
{
    public function testMethodWithAttribute(
        #[Stub('Extra_Activity_ActivityMethod', args: ['method' => 'withAttribute'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('array');
        self::assertEquals(1, $result['result']);
        self::assertCount(0, $result['deprecations'], \print_r($result['deprecations'], true));
    }

    public function testMethodWithoutAttribute(
        #[Stub('Extra_Activity_ActivityMethod', args: ['method' => 'withoutAttribute'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('array');
        self::assertEquals(2, $result['result']);
        self::assertCount(1, $result['deprecations']);
        self::assertEquals(
            \sprintf(
                'Using implicit activity methods is deprecated. Explicitly mark activity method %s with #[%s] attribute instead.',
                TestActivity::class . '::withoutAttribute',
                ActivityMethod::class,
            ),
            $result['deprecations'][0]['message'],
        );
    }

    public function testMagicMethodIsIgnored(
        #[Stub('Extra_Activity_ActivityMethod', args: ['method' => '__invoke'])]
        WorkflowStubInterface $stub,
    ): void {
        $this->expectException(WorkflowFailedException::class);
        $stub->getResult(type: 'int');
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Activity_ActivityMethod")]
    public function handle(string $method)
    {
        $activityStub = Workflow::newActivityStub(
            TestActivity::class,
            Activity\ActivityOptions::new()->withScheduleToCloseTimeout(10),
        );
        $result = yield $activityStub->{$method}();

        return [
            'result' => $result,
            'deprecations' => DeprecationCollector::getAll(),
        ];
    }
}

#[Activity\ActivityInterface(prefix: 'Extra_Activity_ActivityMethod.')]
class TestActivity
{
    #[ActivityMethod]
    public function withAttribute()
    {
        return 1;
    }

    public function withoutAttribute()
    {
        return 2;
    }

    public function __invoke()
    {
        return 3;
    }
}
