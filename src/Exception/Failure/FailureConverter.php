<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Nexus\Sdk\Exception\HandlerException as NexusHandlerException;
use Nexus\Sdk\Exception\OperationException as NexusOperationException;
use Nexus\Sdk\Exception\RetryBehavior as NexusRetryBehavior;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Api\Failure\V1\ActivityFailureInfo;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\CanceledFailureInfo;
use Temporal\Api\Failure\V1\ChildWorkflowExecutionFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Failure\V1\NexusHandlerFailureInfo;
use Temporal\Api\Failure\V1\NexusOperationFailureInfo;
use Temporal\Api\Failure\V1\ServerFailureInfo;
use Temporal\Api\Failure\V1\TerminatedFailureInfo;
use Temporal\Api\Failure\V1\TimeoutFailureInfo;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Internal\Support\DateInterval;

final class FailureConverter
{
    /**
     * Tag inserted into {@see ApplicationFailureInfo::$type} so that RR can tell a
     * Nexus handler-side OperationException apart from other application failures.
     * The full type becomes e.g. "nexus.OperationError.failed".
     *
     * @internal Wire contract with roadrunner-temporal.
     */
    public const NEXUS_OPERATION_ERROR_TYPE_PREFIX = 'nexus.OperationError.';

    public static function mapFailureToException(Failure $failure, DataConverterInterface $converter): TemporalFailure
    {
        $e = self::createFailureException($failure, $converter);
        $e->setFailure($failure);

        if ($failure->getStackTrace() !== '') {
            $e->setOriginalStackTrace($failure->getStackTrace());
        }

        return $e;
    }

    public static function mapExceptionToFailure(\Throwable $e, DataConverterInterface $converter): Failure
    {
        $failure = new Failure();

        if ($e instanceof TemporalFailure) {
            $e->setDataConverter($converter);

            if ($e->getFailure() !== null) {
                if ($e->hasOriginalStackTrace() && $e->getFailure()->getStackTrace() === '') {
                    $e->getFailure()->setStackTrace($e->getOriginalStackTrace());
                }

                return $e->getFailure();
            }
        }

        $failure->setMessage($e->getMessage());

        $failure->setSource('PHP_SDK')->setStackTrace(self::generateStackTraceString($e));

        if ($e->getPrevious() !== null) {
            $failure->setCause(self::mapExceptionToFailure($e->getPrevious(), $converter));
        }

        switch (true) {
            case $e instanceof ApplicationFailure:
                $info = new ApplicationFailureInfo();
                $info->setType($e->getType());
                $info->setNonRetryable($e->isNonRetryable());
                $info->setCategory($e->getApplicationErrorCategory()->value);

                // Set Next Retry Delay
                $nextRetry = DateInterval::toDuration($e->getNextRetryDelay());
                $nextRetry === null or $info->setNextRetryDelay($nextRetry);

                if (!$e->getDetails()->isEmpty()) {
                    $info->setDetails($e->getDetails()->toPayloads());
                }

                $failure->setApplicationFailureInfo($info);
                break;

            case $e instanceof TimeoutFailure:
                $info = new TimeoutFailureInfo();

                if (!$e->getLastHeartbeatDetails()->isEmpty()) {
                    $info->setLastHeartbeatDetails($e->getLastHeartbeatDetails()->toPayloads());
                }

                $failure->setTimeoutFailureInfo($info);
                break;

            case $e instanceof CanceledFailure:
                $info = new CanceledFailureInfo();

                if (!$e->getDetails()->isEmpty()) {
                    $info->setDetails($e->getDetails()->toPayloads());
                }

                $failure->setCanceledFailureInfo($info);
                break;

            case $e instanceof TerminatedFailure:
                $failure->setTerminatedFailureInfo(new TerminatedFailureInfo());
                break;

            case $e instanceof ServerFailure:
                $failure->setServerFailureInfo(new ServerFailureInfo());
                break;

            case $e instanceof ActivityFailure:
                $info = new ActivityFailureInfo();
                $info
                    ->setActivityId($e->getActivityId())
                    ->setActivityType(new ActivityType([
                        'name' => $e->getActivityType(),
                    ]))
                    ->setIdentity($e->getIdentity())
                    ->setRetryState($e->getRetryState())
                    ->setScheduledEventId($e->getScheduledEventId())
                    ->setStartedEventId($e->getStartedEventId());

                $failure->setActivityFailureInfo($info);
                break;

            case $e instanceof ChildWorkflowFailure:
                $info = new ChildWorkflowExecutionFailureInfo();
                $info
                    ->setInitiatedEventId($e->getInitiatedEventId())
                    ->setStartedEventId($e->getStartedEventId())
                    ->setNamespace($e->getNamespace())
                    ->setRetryState($e->getRetryState())
                    ->setWorkflowType(new WorkflowType([
                        'name' => $e->getWorkflowType(),
                    ]))
                    ->setWorkflowExecution(new WorkflowExecution([
                        'workflow_id' => $e->getExecution()->getID(),
                        'run_id' => $e->getExecution()->getRunID(),
                    ]));

                $failure->setChildWorkflowExecutionFailureInfo($info);
                break;

            case $e instanceof ActivityCanceledException:
                $failure->setCanceledFailureInfo(new CanceledFailureInfo());
                break;

            case $e instanceof NexusHandlerException:
                $info = new NexusHandlerFailureInfo();
                // Preserve the raw error-type string (SDK allows unknown raw types).
                $info->setType($e->rawErrorType);
                $info->setRetryBehavior(self::mapNexusRetryBehavior($e->retryBehavior));

                $failure->setNexusHandlerFailureInfo($info);
                break;

            case $e instanceof NexusOperationException:
                // Business-level Nexus operation failure (state=failed|canceled).
                // Temporal proto does not yet ship a dedicated failure_info for
                // handler-side operation errors, so we encode the state in a
                // tagged ApplicationFailureInfo that RR knows how to decode.
                $info = new ApplicationFailureInfo();
                $info->setType(self::NEXUS_OPERATION_ERROR_TYPE_PREFIX . $e->state->value);
                $info->setNonRetryable(true);

                $failure->setApplicationFailureInfo($info);
                break;

            default:
                $info = new ApplicationFailureInfo();
                $info->setType($e::class);
                $info->setNonRetryable(false);
                $failure->setApplicationFailureInfo($info);
        }

        return $failure;
    }

    private static function createFailureException(Failure $failure, DataConverterInterface $converter): TemporalFailure
    {
        $previous = null;

        if ($failure->hasCause()) {
            $previous = self::mapFailureToException($failure->getCause(), $converter);
        }

        switch (true) {
            case $failure->hasApplicationFailureInfo():
                $info = $failure->getApplicationFailureInfo();
                \assert($info instanceof ApplicationFailureInfo);

                $details = $info->hasDetails()
                    ? EncodedValues::fromPayloads($info->getDetails(), $converter)
                    : EncodedValues::empty();

                return new ApplicationFailure(
                    $failure->getMessage(),
                    $info->getType(),
                    $info->getNonRetryable(),
                    $details,
                    $previous,
                    DateInterval::parseOrNull($info->getNextRetryDelay()),
                    ApplicationErrorCategory::tryFrom($info->getCategory()) ?? ApplicationErrorCategory::Unspecified,
                );

            case $failure->hasTimeoutFailureInfo():
                $info = $failure->getTimeoutFailureInfo();
                \assert($info instanceof TimeoutFailureInfo);

                $details = $info->hasLastHeartbeatDetails()
                    ? EncodedValues::fromPayloads($info->getLastHeartbeatDetails(), $converter)
                    : EncodedValues::empty()
                ;

                return new TimeoutFailure($failure->getMessage(), $details, $info->getTimeoutType(), $previous);

            case $failure->hasCanceledFailureInfo():
                $info = $failure->getCanceledFailureInfo();
                \assert($info instanceof CanceledFailureInfo);

                $details = $info->hasDetails()
                    ? EncodedValues::fromPayloads($info->getDetails(), $converter)
                    : EncodedValues::empty()
                ;

                return new CanceledFailure($failure->getMessage(), $details, $previous);

            case $failure->hasTerminatedFailureInfo():
                return new TerminatedFailure($failure->getMessage(), $previous);

            case $failure->hasServerFailureInfo():
                $info = $failure->getServerFailureInfo();
                \assert($info instanceof ServerFailureInfo);
                return new ServerFailure($failure->getMessage(), $info->getNonRetryable(), $previous);

            case $failure->hasResetWorkflowFailureInfo():
                $info = $failure->getResetWorkflowFailureInfo();
                $details = $info->hasLastHeartbeatDetails()
                    ? EncodedValues::fromPayloads($info->getLastHeartbeatDetails(), $converter)
                    : EncodedValues::empty();

                return new ApplicationFailure(
                    $failure->getMessage(),
                    'ResetWorkflow',
                    false,
                    $details,
                    $previous,
                );

            case $failure->hasActivityFailureInfo():
                $info = $failure->getActivityFailureInfo();
                \assert($info instanceof ActivityFailureInfo);

                return new ActivityFailure(
                    $info->getScheduledEventId(),
                    $info->getStartedEventId(),
                    $info->getActivityType()->getName(),
                    $info->getActivityId(),
                    $info->getRetryState(),
                    $info->getIdentity(),
                    $previous,
                );

            case $failure->hasChildWorkflowExecutionFailureInfo():
                $info = $failure->getChildWorkflowExecutionFailureInfo();
                $execution = $info->getWorkflowExecution();
                \assert($execution instanceof WorkflowExecution);

                return new ChildWorkflowFailure(
                    $info->getInitiatedEventId(),
                    $info->getStartedEventId(),
                    $info->getWorkflowType()->getName(),
                    new \Temporal\Workflow\WorkflowExecution(
                        $execution->getWorkflowId(),
                        $execution->getRunId(),
                    ),
                    $info->getNamespace(),
                    $info->getRetryState(),
                    $previous,
                );

            case $failure->hasNexusHandlerFailureInfo():
                $info = $failure->getNexusHandlerFailureInfo();
                \assert($info instanceof NexusHandlerFailureInfo);

                return new NexusHandlerFailure(
                    $failure->getMessage(),
                    $info->getType(),
                    $info->getRetryBehavior(),
                    $previous,
                );

            case $failure->hasNexusOperationExecutionFailureInfo():
                $info = $failure->getNexusOperationExecutionFailureInfo();
                \assert($info instanceof NexusOperationFailureInfo);

                // `operation_token` is the canonical field; fall back to the
                // deprecated `operation_id` so older servers still round-trip.
                $token = $info->getOperationToken();
                if ($token === '') {
                    $token = $info->getOperationId();
                }

                return new NexusOperationFailure(
                    $failure->getMessage(),
                    (int) $info->getScheduledEventId(),
                    $info->getEndpoint(),
                    $info->getService(),
                    $info->getOperation(),
                    $token,
                    $previous,
                );

            default:
                throw new \InvalidArgumentException('Failure info not set');
        }
    }

    /**
     * Translate a Nexus SDK RetryBehavior enum to the Temporal proto enum value.
     */
    private static function mapNexusRetryBehavior(NexusRetryBehavior $behavior): int
    {
        return match ($behavior) {
            NexusRetryBehavior::Retryable => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_RETRYABLE,
            NexusRetryBehavior::NonRetryable => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE,
            NexusRetryBehavior::Unspecified => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_UNSPECIFIED,
        };
    }

    private static function generateStackTraceString(\Throwable $e, bool $skipInternal = true): string
    {
        /** @var list<array{
         *     function?: non-empty-string|null,
         *     line?: int<0, max>|null,
         *     file?: non-empty-string|null,
         *     class?: class-string|null,
         *     object?: object|null,
         *     type?: string|null,
         *     args?: array|null
         * }> $frames
         */
        $frames = $e->getTrace();

        $numPad = \strlen((string) (\count($frames) - 1)) + 2;
        // Skipped frames
        $internals = [];
        $isFirst = true;
        $result = [];

        foreach ($frames as $i => $frame) {
            if (!\is_array($frame)) {
                continue;
            }

            $renderer = static fn(): string => \sprintf(
                "%s%s%s\n%s%s%s%s(%s)",
                \str_pad("#$i", $numPad, ' '),
                $frame['file'] ?? '[internal function]',
                isset($frame['line']) ? ":{$frame['line']}" : '',
                \str_repeat(' ', $numPad),
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '',
                self::renderTraceAttributes($frame['args'] ?? []),
            );

            if ($skipInternal && \str_starts_with($frame['class'] ?? '', 'Temporal\\')) {
                if (!$isFirst) {
                    $internals[] = $renderer;
                    $isFirst = false;
                    continue;
                }

                $skipInternal = false;
            }

            $isFirst = false;
            if (\count($internals) > 2) {
                $result[] = \sprintf(
                    '[%d hidden internal calls]',
                    \count($internals),
                );
            } else {
                $result = [...$result, ...\array_map(static fn(callable $renderer) => $renderer(), $internals)];
            }

            $internals = [];
            $result[] = $renderer();
        }

        if ($internals !== []) {
            $result[] = \sprintf('[%d hidden internal calls]', \count($internals));
        }

        return \implode("\n", $result);
    }

    private static function renderTraceAttributes(array $args): string
    {
        if ($args === []) {
            return '';
        }

        $result = [];
        foreach ($args as $arg) {
            $result[] = match (true) {
                $arg => 'true',
                $arg === false => 'false',
                $arg === null => 'null',
                \is_array($arg) => 'array(' . \count($arg) . ')',
                \is_object($arg) => \get_class($arg),
                \is_string($arg) => (string) \json_encode(
                    \strlen($arg) > 50
                        ? \substr($arg, 0, 50) . '...'
                        : $arg,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                ),
                \is_scalar($arg) => (string) $arg,
                default => \get_debug_type($arg),
            };
        }

        return \implode(',', $result);
    }
}
