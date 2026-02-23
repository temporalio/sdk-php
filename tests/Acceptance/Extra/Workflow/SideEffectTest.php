<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\SideEffect;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\SideEffectOptions;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SideEffectTest extends TestCase
{
    #[Test]
    public static function currentTime(
        #[Stub('Extra_Workflow_SideEffect')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');

        self::assertEquals($result['system'], $result['current']);
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Extra_Workflow_SideEffect')]
    public function run()
    {
        yield Workflow::timer('1 seconds');

        /**
         * @var \DateTimeImmutable $currentDate
         */
        $currentDate = yield Workflow::sideEffect(
            static fn(): \DateTimeImmutable => new \DateTimeImmutable(),
            SideEffectOptions::new()
                ->withSummary('Side Effect Summary'),
        );

        return yield [
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
