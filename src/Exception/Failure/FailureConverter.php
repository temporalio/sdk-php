<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Psr\Log\LoggerInterface;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Failure\V1\ActivityFailureInfo;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\CanceledFailureInfo;
use Temporal\Api\Failure\V1\ChildWorkflowExecutionFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Failure\V1\ServerFailureInfo;
use Temporal\Api\Failure\V1\TerminatedFailureInfo;
use Temporal\Api\Failure\V1\TimeoutFailureInfo;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityCanceledException;

final class FailureConverter
{
    /**
     * @var LoggerInterface|null
     */
    private static ?LoggerInterface $logger;

    /**
     * @param Failure $failure
     * @param DataConverterInterface $converter
     * @return TemporalFailure
     */
    public static function mapFailureToException(Failure $failure, DataConverterInterface $converter): TemporalFailure
    {
        $e = self::createFailureException($failure, $converter);
        $e->setFailure($failure);

        if ($failure->getStackTrace() !== '') {
            $e->setOriginalStackTrace($failure->getStackTrace());
        }

        return $e;
    }

    /**
     * @param \Throwable $e
     * @param DataConverterInterface $converter
     * @return Failure
     */
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

        $failure->setSource('PHP_SDK')->setStackTrace((string)$e);

        if ($e->getPrevious() !== null) {
            $failure->setCause(self::mapExceptionToFailure($e->getPrevious(), $converter));
        }

        switch (true) {
            case $e instanceof ApplicationFailure:
                $info = new ApplicationFailureInfo();
                $info->setType($e->getType());
                $info->setNonRetryable($e->isNonRetryable());

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
                        'name' => $e->getActivityType()
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
                        'name' => $e->getWorkflowType()
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

            default:
                $info = new ApplicationFailureInfo();
                $info->setType(get_class($e));
                $info->setNonRetryable(false);
                $failure->setApplicationFailureInfo($info);
        }

        return $failure;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * @param Failure $failure
     * @param DataConverterInterface $converter
     * @return TemporalFailure
     */
    private static function createFailureException(Failure $failure, DataConverterInterface $converter): TemporalFailure
    {
        $previous = null;

        if ($failure->hasCause()) {
            $previous = self::mapFailureToException($failure->getCause(), $converter);
        }

        switch (true) {
            case $failure->hasApplicationFailureInfo():
                $info = $failure->getApplicationFailureInfo();

                $details = $info->hasDetails()
                    ? EncodedValues::fromPayloads($info->getDetails(), $converter)
                    : EncodedValues::empty()
                ;

                return new ApplicationFailure(
                    $failure->getMessage(),
                    $info->getType(),
                    $info->getNonRetryable(),
                    $details,
                    $previous
                );

            case $failure->hasTimeoutFailureInfo():
                $info = $failure->getTimeoutFailureInfo();

                $details = $info->hasLastHeartbeatDetails()
                    ? EncodedValues::fromPayloads($info->getLastHeartbeatDetails(), $converter)
                    : EncodedValues::empty()
                ;

                return new TimeoutFailure($failure->getMessage(), $details, $info->getTimeoutType(), $previous);

            case $failure->hasCanceledFailureInfo():
                $info = $failure->getCanceledFailureInfo();

                $details = $info->hasDetails()
                    ? EncodedValues::fromPayloads($info->getDetails(), $converter)
                    : EncodedValues::empty()
                ;

                return new CanceledFailure($failure->getMessage(), $details, $previous);

            case $failure->hasTerminatedFailureInfo():
                return new TerminatedFailure($failure->getMessage(), $previous);

            case $failure->hasServerFailureInfo():
                $info = $failure->getServerFailureInfo();
                return new ServerFailure($failure->getMessage(), $info->getNonRetryable(), $previous);

            case $failure->hasResetWorkflowFailureInfo():
                $info = $failure->getResetWorkflowFailureInfo();
                $details = $info->hasLastHeartbeatDetails()
                    ? EncodedValues::fromPayloads($info->getLastHeartbeatDetails(), $converter)
                    : EncodedValues::empty()
                ;

                return new ApplicationFailure(
                    $failure->getMessage(),
                    'ResetWorkflow',
                    false,
                    $details,
                    $previous
                );

            case $failure->hasActivityFailureInfo():
                $info = $failure->getActivityFailureInfo();

                return new ActivityFailure(
                    $info->getScheduledEventId(),
                    $info->getStartedEventId(),
                    $info->getActivityType()->getName(),
                    $info->getActivityId(),
                    $info->getRetryState(),
                    $info->getIdentity(),
                    $previous
                );

            case $failure->hasChildWorkflowExecutionFailureInfo():
                $info = $failure->getChildWorkflowExecutionFailureInfo();
                $execution = $info->getWorkflowExecution();

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
                    $previous
                );

            default:
                throw new \InvalidArgumentException('Failure info not set');
        }
    }
}
