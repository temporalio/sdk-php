<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Activity;

use PHPUnit\Framework\Attributes\Test;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Interceptor\Header;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Workflow\WorkflowExecution;

final class ActivityContextTestCase extends AbstractUnit
{
    #[Test]
    public function heartbeatRecognizesApiNativePauseField(): void
    {
        $context = $this->createContext(['activity_paused' => true]);

        try {
            $context->heartbeat('test');
            self::fail('Activity pause was not propagated from the heartbeat response');
        } catch (ActivityPausedException $e) {
            self::assertSame('activity-id', $e->getActivityId());
        }

        self::assertFalse($context->getCancellationDetails()?->cancelRequested ?? true);
        self::assertTrue($context->getCancellationDetails()?->paused ?? false);
    }

    #[Test]
    public function heartbeatRecognizesApiNativeCancelField(): void
    {
        $context = $this->createContext(['cancel_requested' => true]);

        try {
            $context->heartbeat('test');
            self::fail('Activity cancellation was not propagated from the heartbeat response');
        } catch (ActivityCanceledException $e) {
            self::assertSame('activity-id', $e->getActivityId());
        }

        self::assertTrue($context->getCancellationDetails()?->cancelRequested ?? false);
        self::assertFalse($context->getCancellationDetails()?->paused ?? true);
    }

    /**
     * @param array<string, bool> $response
     */
    private function createContext(array $response): ActivityContext
    {
        $rpc = $this->createMock(RPCConnectionInterface::class);
        $rpc->expects(self::once())
            ->method('call')
            ->with(
                'temporal.RecordActivityHeartbeat',
                self::callback(static fn(array $payload): bool => isset($payload['taskToken'], $payload['details'])),
            )
            ->willReturn($response);

        $context = new ActivityContext(
            $rpc,
            DataConverter::createDefault(),
            EncodedValues::empty(),
            Header::empty(),
        );

        $info = $context->getInfo();
        $info->workflowExecution = new WorkflowExecution('workflow-id', 'run-id');
        $info->id = 'activity-id';
        $info->type->name = 'activity-type';

        return $context;
    }
}
