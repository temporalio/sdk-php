<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Activity;

use PHPUnit\Framework\Attributes\Test;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Client\ActivityCompletionException;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Exception\Client\ServiceClientException;
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

    #[Test]
    public function heartbeatHappyPathDoesNotThrow(): void
    {
        $context = $this->createContext([]);

        $context->heartbeat('test');

        self::assertNull($context->getCancellationDetails());
    }

    #[Test]
    public function heartbeatRecognizesLegacyCanceledFlag(): void
    {
        $context = $this->createContext(['canceled' => true]);

        $this->expectException(ActivityCanceledException::class);
        $context->heartbeat('test');
    }

    #[Test]
    public function heartbeatRecognizesCamelCaseCancelRequestedFlag(): void
    {
        $context = $this->createContext(['cancelRequested' => true]);

        $this->expectException(ActivityCanceledException::class);
        $context->heartbeat('test');
    }

    #[Test]
    public function heartbeatRecognizesLegacyPausedFlag(): void
    {
        $context = $this->createContext(['paused' => true]);

        $this->expectException(ActivityPausedException::class);
        $context->heartbeat('test');
    }

    #[Test]
    public function heartbeatRecognizesCamelCaseActivityPausedFlag(): void
    {
        $context = $this->createContext(['activityPaused' => true]);

        $this->expectException(ActivityPausedException::class);
        $context->heartbeat('test');
    }

    #[Test]
    public function heartbeatWithBothCancelAndPauseThrowsCanceled(): void
    {
        // When both flags are set, cancel takes precedence
        $context = $this->createContext(['cancel_requested' => true, 'activity_paused' => true]);

        try {
            $context->heartbeat('test');
            self::fail('Expected exception');
        } catch (ActivityCanceledException) {
            // Cancel takes precedence
        }

        $details = $context->getCancellationDetails();
        self::assertNotNull($details);
        self::assertTrue($details->cancelRequested);
        self::assertTrue($details->paused);
    }

    #[Test]
    public function heartbeatServiceClientExceptionWrappedInActivityCompletionException(): void
    {
        $rpc = $this->createMock(RPCConnectionInterface::class);
        $rpc->expects(self::once())
            ->method('call')
            ->willThrowException(
                new ServiceClientException((object) ['code' => 13, 'metadata' => []]),
            );

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

        try {
            $context->heartbeat('test');
            self::fail('Expected ActivityCompletionException');
        } catch (ActivityCompletionException $e) {
            self::assertSame('activity-id', $e->getActivityId());
            self::assertInstanceOf(ServiceClientException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function heartbeatFalseResponseFlagsDoNotTriggerException(): void
    {
        $context = $this->createContext(['cancel_requested' => false, 'activity_paused' => false]);

        $context->heartbeat('test');

        self::assertNull($context->getCancellationDetails());
    }

    #[Test]
    public function heartbeatNonArrayResponseDoesNotThrow(): void
    {
        $rpc = $this->createMock(RPCConnectionInterface::class);
        $rpc->expects(self::once())
            ->method('call')
            ->willReturn('ok');

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

        $context->heartbeat('test');

        self::assertNull($context->getCancellationDetails());
    }

    #[Test]
    public function heartbeatObjectResponseIsConvertedToArray(): void
    {
        $rpc = $this->createMock(RPCConnectionInterface::class);
        $rpc->expects(self::once())
            ->method('call')
            ->willReturn((object) ['cancel_requested' => true]);

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

        $this->expectException(ActivityCanceledException::class);
        $context->heartbeat('test');
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
