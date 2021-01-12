<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception\Failure;

use Psr\Log\LoggerInterface;
use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;

final class FailureConverter
{
    /**
     * @var LoggerInterface|null
     */
    private static ?LoggerInterface $logger;

    /**
     *   public static RuntimeException failureToException(Failure failure, DataConverter dataConverter) {
     * if (failure == null) {
     * return null;
     * }
     * RuntimeException result = failureToExceptionImpl(failure, dataConverter);
     * if (result instanceof TemporalFailure) {
     * ((TemporalFailure) result).setFailure(failure);
     * }
     * if (failure.getSource().equals(JAVA_SDK) && !failure.getStackTrace().isEmpty()) {
     * StackTraceElement[] stackTrace = parseStackTrace(failure.getStackTrace());
     * result.setStackTrace(stackTrace);
     * }
     * return result;
     * }
     */

    /**
     * @param Failure $failure
     * @param DataConverterInterface $converter
     * @return \Throwable
     */
    public static function mapFailureToException(Failure $failure, DataConverterInterface $converter): \Throwable
    {
        $previous = null;
        if ($failure->hasCause()) {
            $previous = self::mapFailureToException($failure->getCause(), $converter);
        }

        // todo: do we have constants for that?
        switch ($failure->getFailureInfo()) {
            case 'APPLICATION_FAILURE_INFO':
                $info = $failure->getApplicationFailureInfo();

                if ($info->hasDetails()) {
                    $details = EncodedValues::createFromPayloads($info->getDetails(), $converter);
                } else {
                    $details = EncodedValues::createEmpty();
                }

                return new ApplicationFailure(
                    $failure->getMessage(),
                    $info->getType(),
                    $info->getNonRetryable(),
                    $details,
                    $previous
                );
            case 'TIMEOUT_FAILURE_INFO':
                $info = $failure->getTimeoutFailureInfo();
                if ($info->hasLastHeartbeatDetails()) {
                    $details = EncodedValues::createFromPayloads($info->getLastHeartbeatDetails(), $converter);
                } else {
                    $details = EncodedValues::createEmpty();
                }

                return new TimeoutFailure(
                    $failure->getMessage(),
                    $details,
                    $info->getTimeoutType(),
                    $previous
                );
            case 'CANCELED_FAILURE_INFO':
                $info = $failure->getCanceledFailureInfo();
                if ($info->hasDetails()) {
                    $details = EncodedValues::createFromPayloads($info->getDetails(), $converter);
                } else {
                    $details = EncodedValues::createEmpty();
                }

                return new CanceledFailure($failure->getMessage(), $details, $previous);
            case 'TERMINATED_FAILURE_INFO':
                return new TerminatedFailure($failure->getMessage(), $previous);
            case 'SERVER_FAILURE_INFO':
                $info = $failure->getServerFailureInfo();
                return new ServerFailure($failure->getMessage(), $info->getNonRetryable(), $previous);
            case 'RESET_WORKFLOW_FAILURE_INFO':
                $info = $failure->getResetWorkflowFailureInfo();
                if ($info->hasLastHeartbeatDetails()) {
                    $details = EncodedValues::createFromPayloads($info->getLastHeartbeatDetails(), $converter);
                } else {
                    $details = EncodedValues::createEmpty();
                }

                return new ApplicationFailure(
                    $failure->getMessage(),
                    'ResetWorkflow',
                    false,
                    $details,
                    $previous
                );
            case 'ACTIVITY_FAILURE_INFO':
                $info = $failure->getActivityFailureInfo();

                // todo: complete constructor
                return new ActivityFailure(
                    $info->getScheduledEventId(),
                    $info->getStartedEventId(),
                    $info->getActivityType()->getName(),
                    $info->getActivityId(),
                    $info->getRetryState(),
                    $info->getIdentity(),
                    $previous
                );
            case 'CHILD_WORKFLOW_EXECUTION_FAILURE_INFO':
                $info = $failure->getChildWorkflowExecutionFailureInfo();

                // todo: complete constructor
                return new ChildWorkflowFailure(
                    $info->getInitiatedEventId(),
                    $info->getStartedEventId(),
                    $info->getWorkflowType()->getName(),
                    $info->getWorkflowExecution(),
                    $info->getNamespace(),
                    $info->getRetryState(),
                    $previous
                );
            case 'FAILUREINFO_NOT_SET':
                throw new \InvalidArgumentException('Failure info not set');
        }
    }

    /**
     * @param \Throwable $e
     * @param DataConverterInterface $dataConverter
     * @return Failure
     */
    public static function mapExceptionToFailure(\Throwable $e, DataConverterInterface $dataConverter): Failure
    {
        $failure = new Failure();

        if ($e instanceof TemporalFailure && $e->getFailure() !== null) {
            return $e->getFailure();
        } else {
            $failure->setMessage($e->getMessage());
        }

        $failure->setSource('PHP_SDK')->setStackTrace((string)$e);

        if ($e->getPrevious() !== null) {
            $failure->setCause(self::mapExceptionToFailure($e->getPrevious(), $dataConverter));
        }

//    String stackTrace = serializeStackTrace(e);
//    if (e.getCause() != null) {
//        failure.setCause(exceptionToFailure(e.getCause()));
//    }
//    if (e instanceof ApplicationFailure) {
//        ApplicationFailure ae = (ApplicationFailure) e;
//      ApplicationFailureInfo.Builder info =
//            ApplicationFailureInfo.newBuilder()
//            .setType(ae.getType())
//            .setNonRetryable(ae.isNonRetryable());
//      Optional<Payloads> details = ((EncodedValues) ae.getDetails()).toPayloads();
//      if (details.isPresent()) {
//          info.setDetails(details.get());
//      }
//      failure.setApplicationFailureInfo(info);
//    } else if (e instanceof TimeoutFailure) {
//        TimeoutFailure te = (TimeoutFailure) e;
//      TimeoutFailureInfo.Builder info =
//            TimeoutFailureInfo.newBuilder().setTimeoutType(te.getTimeoutType());
//      Optional<Payloads> details = ((EncodedValues) te.getLastHeartbeatDetails()).toPayloads();
//      if (details.isPresent()) {
//          info.setLastHeartbeatDetails(details.get());
//      }
//      failure.setTimeoutFailureInfo(info);
//    } else if (e instanceof CanceledFailure) {
//        CanceledFailure ce = (CanceledFailure) e;
//      CanceledFailureInfo.Builder info = CanceledFailureInfo.newBuilder();
//      Optional<Payloads> details = ((EncodedValues) ce.getDetails()).toPayloads();
//      if (details.isPresent()) {
//          info.setDetails(details.get());
//      }
//      failure.setCanceledFailureInfo(info);
//    } else if (e instanceof TerminatedFailure) {
//        TerminatedFailure te = (TerminatedFailure) e;
//      failure.setTerminatedFailureInfo(TerminatedFailureInfo.getDefaultInstance());
//    } else if (e instanceof ServerFailure) {
//        ServerFailure se = (ServerFailure) e;
//      failure.setServerFailureInfo(
//          ServerFailureInfo.newBuilder().setNonRetryable(se.isNonRetryable()));
//    } else if (e instanceof ActivityFailure) {
//        ActivityFailure ae = (ActivityFailure) e;
//      ActivityFailureInfo.Builder info =
//            ActivityFailureInfo.newBuilder()
//            .setActivityId(ae.getActivityId() == null ? "" : ae.getActivityId())
//            .setActivityType(ActivityType.newBuilder().setName(ae.getActivityType()))
//            .setIdentity(ae.getIdentity())
//            .setRetryState(ae.getRetryState())
//            .setScheduledEventId(ae.getScheduledEventId())
//            .setStartedEventId(ae.getStartedEventId());
//      failure.setActivityFailureInfo(info);
//    } else if (e instanceof ChildWorkflowFailure) {
//        ChildWorkflowFailure ce = (ChildWorkflowFailure) e;
//      ChildWorkflowExecutionFailureInfo.Builder info =
//            ChildWorkflowExecutionFailureInfo.newBuilder()
//            .setInitiatedEventId(ce.getInitiatedEventId())
//            .setStartedEventId(ce.getStartedEventId())
//            .setNamespace(ce.getNamespace() == null ? "" : ce.getNamespace())
//            .setRetryState(ce.getRetryState())
//            .setWorkflowType(WorkflowType.newBuilder().setName(ce.getWorkflowType()))
//            .setWorkflowExecution(ce.getExecution());
//      failure.setChildWorkflowExecutionFailureInfo(info);
//    } else if (e instanceof ActivityCanceledException) {
//        CanceledFailureInfo.Builder info = CanceledFailureInfo.newBuilder();
//      failure.setCanceledFailureInfo(info);
//    } else {
//        ApplicationFailureInfo.Builder info =
//            ApplicationFailureInfo.newBuilder()
//            .setType(e.getClass().getName())
//            .setNonRetryable(false);
//      failure.setApplicationFailureInfo(info);
//    }
//    return failure.build();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }
}
