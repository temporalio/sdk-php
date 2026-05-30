<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Google\Rpc\Code;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\WorkflowException;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Internal\Nexus\HandlerErrorMapper;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\RetryBehavior;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(HandlerErrorMapper::class)]
#[UsesClass(HandlerException::class)]
#[UsesClass(ErrorType::class)]
#[UsesClass(RetryBehavior::class)]
final class HandlerErrorMapperTestCase extends AbstractUnit
{
    /**
     * @return iterable<string, array{0: int, 1: ErrorType, 2: RetryBehavior}>
     */
    public static function grpcCodeMatrix(): iterable
    {
        // Mirrors Go internal_nexus_task_handler.go:592-647 and Java NexusTaskHandlerImpl.java:236-269.
        yield 'INVALID_ARGUMENT → BadRequest' => [Code::INVALID_ARGUMENT, ErrorType::BadRequest, RetryBehavior::Unspecified];
        yield 'ALREADY_EXISTS → Internal/NonRetryable' => [Code::ALREADY_EXISTS, ErrorType::Internal, RetryBehavior::NonRetryable];
        yield 'FAILED_PRECONDITION → Internal/NonRetryable' => [Code::FAILED_PRECONDITION, ErrorType::Internal, RetryBehavior::NonRetryable];
        yield 'OUT_OF_RANGE → Internal/NonRetryable' => [Code::OUT_OF_RANGE, ErrorType::Internal, RetryBehavior::NonRetryable];
        yield 'ABORTED → Unavailable' => [Code::ABORTED, ErrorType::Unavailable, RetryBehavior::Unspecified];
        yield 'UNAVAILABLE → Unavailable' => [Code::UNAVAILABLE, ErrorType::Unavailable, RetryBehavior::Unspecified];
        yield 'CANCELLED → Internal' => [Code::CANCELLED, ErrorType::Internal, RetryBehavior::Unspecified];
        yield 'DATA_LOSS → Internal' => [Code::DATA_LOSS, ErrorType::Internal, RetryBehavior::Unspecified];
        yield 'INTERNAL → Internal' => [Code::INTERNAL, ErrorType::Internal, RetryBehavior::Unspecified];
        yield 'UNKNOWN → Internal' => [Code::UNKNOWN, ErrorType::Internal, RetryBehavior::Unspecified];
        yield 'UNAUTHENTICATED → Internal' => [Code::UNAUTHENTICATED, ErrorType::Internal, RetryBehavior::Unspecified];
        yield 'PERMISSION_DENIED → Internal' => [Code::PERMISSION_DENIED, ErrorType::Internal, RetryBehavior::Unspecified];
        yield 'NOT_FOUND → NotFound' => [Code::NOT_FOUND, ErrorType::NotFound, RetryBehavior::Unspecified];
        yield 'RESOURCE_EXHAUSTED → ResourceExhausted' => [Code::RESOURCE_EXHAUSTED, ErrorType::ResourceExhausted, RetryBehavior::Unspecified];
        yield 'UNIMPLEMENTED → NotImplemented' => [Code::UNIMPLEMENTED, ErrorType::NotImplemented, RetryBehavior::Unspecified];
        yield 'DEADLINE_EXCEEDED → UpstreamTimeout' => [Code::DEADLINE_EXCEEDED, ErrorType::UpstreamTimeout, RetryBehavior::Unspecified];
    }

    #[DataProvider('grpcCodeMatrix')]
    public function testMapsGrpcCodeToHandlerException(
        int $code,
        ErrorType $expectedType,
        RetryBehavior $expectedRetry,
    ): void {
        $exception = self::makeServiceClientException($code, 'wire detail');

        $mapped = HandlerErrorMapper::mapToHandlerException($exception);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame($expectedType, $mapped->errorType);
        self::assertSame($expectedRetry, $mapped->retryBehavior);
        self::assertSame($exception, $mapped->getPrevious(), 'Original gRPC exception preserved as cause');
    }

    public function testUnknownGrpcCodeFallsBackToInternal(): void
    {
        $exception = self::makeServiceClientException(9999, 'weird');

        $mapped = HandlerErrorMapper::mapToHandlerException($exception);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame(ErrorType::Internal, $mapped->errorType);
        self::assertSame(RetryBehavior::Unspecified, $mapped->retryBehavior);
    }

    public function testNonRetryableApplicationFailureBecomesInternalNonRetryable(): void
    {
        $cause = new ApplicationFailure(
            'business invariant violated',
            'BusinessError',
            true,
            EncodedValues::empty(),
        );

        $mapped = HandlerErrorMapper::mapToHandlerException($cause);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame(ErrorType::Internal, $mapped->errorType);
        self::assertSame(RetryBehavior::NonRetryable, $mapped->retryBehavior);
        self::assertSame($cause, $mapped->getPrevious());
    }

    public function testRetryableApplicationFailureIsNotMapped(): void
    {
        $cause = new ApplicationFailure(
            'transient',
            'TransientError',
            false,
            EncodedValues::empty(),
        );

        self::assertNull(HandlerErrorMapper::mapToHandlerException($cause));
    }

    public function testWorkflowNotFoundExceptionBecomesNotFound(): void
    {
        $cause = new WorkflowNotFoundException(null, new WorkflowExecution('wf-id', 'run-id'));

        $mapped = HandlerErrorMapper::mapToHandlerException($cause);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame(ErrorType::NotFound, $mapped->errorType);
        self::assertSame(RetryBehavior::Unspecified, $mapped->retryBehavior);
        self::assertSame($cause, $mapped->getPrevious());
    }

    public function testWorkflowExceptionBecomesBadRequest(): void
    {
        $cause = new WorkflowException(null, new WorkflowExecution('wf-id', 'run-id'));

        $mapped = HandlerErrorMapper::mapToHandlerException($cause);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame(ErrorType::BadRequest, $mapped->errorType);
        self::assertSame(RetryBehavior::Unspecified, $mapped->retryBehavior);
        self::assertSame($cause, $mapped->getPrevious());
    }

    public function testWorkflowFailedExceptionBecomesBadRequest(): void
    {
        $cause = new WorkflowFailedException(new WorkflowExecution('wf-id', 'run-id'), 'WorkflowType', 1, 0);

        $mapped = HandlerErrorMapper::mapToHandlerException($cause);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame(ErrorType::BadRequest, $mapped->errorType);
        self::assertSame(RetryBehavior::Unspecified, $mapped->retryBehavior);
        self::assertSame($cause, $mapped->getPrevious());
    }

    public function testWorkflowExecutionAlreadyStartedExceptionBecomesBadRequest(): void
    {
        $cause = new WorkflowExecutionAlreadyStartedException(new WorkflowExecution('wf-id', 'run-id'), 'WorkflowType');

        $mapped = HandlerErrorMapper::mapToHandlerException($cause);

        self::assertInstanceOf(HandlerException::class, $mapped);
        self::assertSame(ErrorType::BadRequest, $mapped->errorType);
        self::assertSame(RetryBehavior::Unspecified, $mapped->retryBehavior);
        self::assertSame($cause, $mapped->getPrevious());
    }

    public function testGenericRuntimeExceptionIsNotMapped(): void
    {
        self::assertNull(HandlerErrorMapper::mapToHandlerException(new \RuntimeException('boom')));
        self::assertNull(HandlerErrorMapper::mapToHandlerException(new \LogicException('boom')));
    }

    private static function makeServiceClientException(int $code, string $detail): ServiceClientException
    {
        $status = new \stdClass();
        $status->code = $code;
        $status->details = $detail;
        $status->metadata = [];

        return new ServiceClientException($status);
    }
}
