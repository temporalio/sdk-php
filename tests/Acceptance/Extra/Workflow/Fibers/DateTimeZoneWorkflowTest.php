<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Fibers\DateTimeZoneWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DateTimeZoneWorkflowTest extends TestCase
{
    #[Test]
    public static function currentTime(
        #[Stub('Extra_Workflow_Fibers_DateTimeZoneWorkflow')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');

        self::assertEquals($result['system'], $result['current']);
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Extra_Workflow_Fibers_DateTimeZoneWorkflow')]
    public function run()
    {
        Workflow::timer('1 seconds');

        /**
         * @var \DateTimeImmutable $currentDate
         */
        $currentDate = Workflow::sideEffect(static fn(): \DateTimeImmutable => new \DateTimeImmutable());

        return [
            'current' => [
                'timestamp' => $currentDate->getTimestamp(),
                'timezone.offset' => $currentDate->getTimeZone()->getOffset($currentDate),
            ],
            'system' => [
                'timestamp' => Workflow::now()->getTimestamp(),
                'timezone.offset' => Workflow::now()->getTimezone()->getOffset(Workflow::now()),
            ],
        ];
    }
}
