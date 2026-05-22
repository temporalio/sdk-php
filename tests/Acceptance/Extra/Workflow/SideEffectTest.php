<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\SideEffect;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Common\V1\Payload;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\SideEffectOptions;
use Temporal\DataConverter\DataConverterInterface;
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

    #[Test]
    public static function summaryRecordedOnMarker(
        #[Stub('Extra_Workflow_SideEffect')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        DataConverterInterface $dataConverter,
    ): void {
        $stub->getResult();

        $summaries = self::collectSideEffectSummaries($client, $stub, $dataConverter);

        self::assertSame(['Side Effect Summary'], $summaries);
    }

    #[Test]
    public static function distinctSummariesPerSideEffect(
        #[Stub('Extra_Workflow_SideEffect_Multi')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        DataConverterInterface $dataConverter,
    ): void {
        $stub->getResult();

        $summaries = self::collectSideEffectSummaries($client, $stub, $dataConverter);

        self::assertSame(['first summary', 'second summary'], $summaries);
    }

    #[Test]
    public static function noSummaryWhenOptionsOmitted(
        #[Stub('Extra_Workflow_SideEffect_NoOptions')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        $stub->getResult();

        $markerCount = 0;
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if (!$event->hasMarkerRecordedEventAttributes()) {
                continue;
            }
            if ($event->getMarkerRecordedEventAttributes()->getMarkerName() !== 'SideEffect') {
                continue;
            }

            ++$markerCount;
            self::assertNull($event->getUserMetadata()?->getSummary());
        }

        self::assertSame(1, $markerCount, 'SideEffect marker must exist in the Workflow history');
    }

    /**
     * @return list<string>
     */
    private static function collectSideEffectSummaries(
        WorkflowClientInterface $client,
        WorkflowStubInterface $stub,
        DataConverterInterface $dataConverter,
    ): array {
        $summaries = [];
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if (!$event->hasMarkerRecordedEventAttributes()) {
                continue;
            }
            if ($event->getMarkerRecordedEventAttributes()->getMarkerName() !== 'SideEffect') {
                continue;
            }

            $payload = $event->getUserMetadata()?->getSummary();
            self::assertInstanceOf(Payload::class, $payload);
            $summaries[] = $dataConverter->fromPayload($payload, 'string');
        }

        return $summaries;
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

#[WorkflowInterface]
class MultiSummaryWorkflow
{
    #[WorkflowMethod('Extra_Workflow_SideEffect_Multi')]
    public function run()
    {
        yield Workflow::sideEffect(
            static fn(): int => 1,
            SideEffectOptions::new()->withSummary('first summary'),
        );
        yield Workflow::sideEffect(
            static fn(): int => 2,
            SideEffectOptions::new()->withSummary('second summary'),
        );
    }
}

#[WorkflowInterface]
class NoOptionsWorkflow
{
    #[WorkflowMethod('Extra_Workflow_SideEffect_NoOptions')]
    public function run()
    {
        yield Workflow::sideEffect(static fn(): int => 42);
    }
}
