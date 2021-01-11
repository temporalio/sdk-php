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


    public static function toException(Failure $failure, DataConverterInterface $converter): \Throwable
    {
        $previous = null;
        if ($failure->hasCause()) {
            $previous = self::toException($failure->getCause(), $converter);
        }

        // todo: do we have constants for that?
        switch ($failure->getFailureInfo()) {
            case 'APPLICATION_FAILURE_INFO':
            case 'TIMEOUT_FAILURE_INFO':
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


//        RuntimeException cause =
//        failure.hasCause() ? failureToException(failure.getCause(), dataConverter) : null;
//    switch (failure.getFailureInfoCase()) {
//        case APPLICATION_FAILURE_INFO:
//        {
//            ApplicationFailureInfo info = failure.getApplicationFailureInfo();
//          // Unwrap SimulatedTimeoutFailure
//          if (failure.getSource().equals(JAVA_SDK)
//              && info.getType().equals(SimulatedTimeoutFailure.class.getName())
//              && cause != null) {
//            return cause;
//        }
//          Optional<Payloads> details =
//            info.hasDetails() ? Optional.of(info.getDetails()) : Optional.empty();
//          return ApplicationFailure.newFromValues(
//                  failure.getMessage(),
//                  info.getType(),
//                  info.getNonRetryable(),
//                  new EncodedValues(details, dataConverter),
//                  cause);
//        }
//        case TIMEOUT_FAILURE_INFO:
//        {
//            TimeoutFailureInfo info = failure.getTimeoutFailureInfo();
//          Optional<Payloads> lastHeartbeatDetails =
//            info.hasLastHeartbeatDetails()
//                ? Optional.of(info.getLastHeartbeatDetails())
//                : Optional.empty();
//          TimeoutFailure tf =
//            new TimeoutFailure(
//                failure.getMessage(),
//                new EncodedValues(lastHeartbeatDetails, dataConverter),
//                info.getTimeoutType(),
//                cause);
//          tf.setStackTrace(new StackTraceElement[0]);
//          return tf;
//
//

//        case ACTIVITY_FAILURE_INFO:
//        {
//            ActivityFailureInfo info = failure.getActivityFailureInfo();
//          return new ActivityFailure(
//              info.getScheduledEventId(),
//              info.getStartedEventId(),
//              info.getActivityType().getName(),
//              info.getActivityId(),
//              info.getRetryState(),
//              info.getIdentity(),
//              cause);
//        }
//        case CHILD_WORKFLOW_EXECUTION_FAILURE_INFO:
//        {
//            ChildWorkflowExecutionFailureInfo info = failure.getChildWorkflowExecutionFailureInfo();
//          return new ChildWorkflowFailure(
//              info.getInitiatedEventId(),
//              info.getStartedEventId(),
//              info.getWorkflowType().getName(),
//              info.getWorkflowExecution(),
//              info.getNamespace(),
//              info.getRetryState(),
//              cause);
//        }
//        case FAILUREINFO_NOT_SET:
//        default:
//            throw new IllegalArgumentException("Failure info not set");
//    }
    }

    public static function toFailure(\Throwable $e, DataConverterInterface $dataConverter): Failure
    {
//        if (e instanceof CheckedExceptionWrapper) {
//            return exceptionToFailure(e.getCause());
//        }
//        String message;
//    if (e instanceof TemporalFailure) {
//        TemporalFailure tf = (TemporalFailure) e;
//      if (tf.getFailure().isPresent()) {
//          return tf.getFailure().get();
//      }
//      message = tf.getOriginalMessage();
//    } else {
//        message = e.getMessage() == null ? "" : e.getMessage();
//    }
//    String stackTrace = serializeStackTrace(e);
//    Failure.Builder failure =
//        Failure.newBuilder().setMessage(message).setSource(JAVA_SDK).setStackTrace(stackTrace);
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
