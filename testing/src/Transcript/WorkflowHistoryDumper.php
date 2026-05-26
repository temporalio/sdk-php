<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

use Google\Protobuf\Timestamp;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

final class WorkflowHistoryDumper
{
    /**
     * Writes a human-readable, single-blob render of each stub's history into
     * the transcript as `workflow_history_render` META events. Use on test
     * failure to capture an at-a-glance view alongside the structured `HISTORY`
     * events produced by {@see self::dump()}.
     *
     * @param array<int, mixed> $args Call arguments; only WorkflowStubInterface entries are inspected.
     */
    public function renderForFailure(
        TranscriptWriter $transcript,
        WorkflowClientInterface $workflowClient,
        array $args,
    ): void {
        foreach ($args as $arg) {
            if (!$arg instanceof WorkflowStubInterface) {
                continue;
            }
            $execution = $arg->getExecution();
            try {
                $text = $this->renderExecution($workflowClient, $arg);
            } catch (\Throwable $renderError) {
                $transcript->writeMeta('workflow_history_render_failed', [
                    'workflow_id' => $execution->getID(),
                    'run_id' => (string) $execution->getRunID(),
                    'class' => $renderError::class,
                    'message' => $renderError->getMessage(),
                ]);
                continue;
            }
            $transcript->writeMeta('workflow_history_render', [
                'workflow_id' => $execution->getID(),
                'run_id' => (string) $execution->getRunID(),
                'text' => $text,
            ]);
        }
    }

    /**
     * @param array<int, mixed> $args Call arguments; WorkflowStubInterface entries contribute their execution.
     */
    public function dump(
        TranscriptWriter $transcript,
        WorkflowClientInterface $workflowClient,
        array $args,
    ): void {
        $executions = [];
        $stubCount = 0;
        foreach ($args as $arg) {
            if (!$arg instanceof WorkflowStubInterface) {
                continue;
            }
            $stubCount++;
            $execution = $arg->getExecution();
            if ($execution->getRunID() === null) {
                $transcript->writeMeta('history_skipped', [
                    'reason' => 'no_run_id',
                    'workflow_id' => $execution->getID(),
                ]);
                continue;
            }
            $key = $execution->getID() . ':' . $execution->getRunID();
            $executions[$key] = $execution;
        }

        if ($executions === [] && $stubCount === 0) {
            $transcript->writeMeta('history_skipped', ['reason' => 'no_executions_inspected']);
            return;
        }

        foreach ($executions as $execution) {
            $this->dumpExecution($transcript, $workflowClient, $execution);
        }
    }

    private function dumpExecution(
        TranscriptWriter $transcript,
        WorkflowClientInterface $workflowClient,
        WorkflowExecution $execution,
    ): void {
        try {
            $eventCount = 0;
            foreach ($workflowClient->getWorkflowHistory($execution) as $event) {
                $eventCount++;
                $eventAttributes = [
                    'event_id' => (int) $event->getEventId(),
                    'event_type' => EventType::name($event->getEventType()),
                ];
                $eventTime = $event->getEventTime();
                if ($eventTime !== null) {
                    $eventAttributes['event_time'] = $eventTime->getSeconds() . '.' . $eventTime->getNanos();
                }
                $payloadJson = '{}';
                try {
                    $payloadJson = $event->serializeToJsonString();
                } catch (\Throwable $serializationError) {
                    $eventAttributes['serialize_error'] = $serializationError->getMessage();
                }
                $transcript->writeHistoryEvent(
                    $execution->getID(),
                    (string) $execution->getRunID(),
                    $eventAttributes,
                    $payloadJson,
                );
            }
            $transcript->writeMeta('history_dumped', [
                'workflow_id' => $execution->getID(),
                'run_id' => $execution->getRunID(),
                'event_count' => $eventCount,
            ]);
        } catch (\Throwable $historyError) {
            $transcript->writeHistoryError($execution->getID(), $historyError);
        }
    }

    private function renderExecution(WorkflowClientInterface $workflowClient, WorkflowStubInterface $stub): string
    {
        $fnTime = static fn(?Timestamp $ts): float => $ts === null
            ? 0
            : $ts->getSeconds() + \round($ts->getNanos() / 1_000_000_000, 6);

        $out = '';
        $start = null;
        foreach ($workflowClient->getWorkflowHistory($stub->getExecution()) as $event) {
            \assert($event instanceof HistoryEvent);
            $start ??= $fnTime($event->getEventTime());
            $deltaMs = \round(1_000 * ($fnTime($event->getEventTime()) - $start));

            $out .= "\n"
                . \str_pad((string) $event->getEventId(), 3, ' ', STR_PAD_LEFT) . ' '
                . \str_pad(\number_format($deltaMs, 0, '.', "'"), 6, ' ', STR_PAD_LEFT) . 'ms  '
                . \str_pad(EventType::name($event->getEventType()), 40, ' ', STR_PAD_RIGHT) . ' ';

            $cause = $event->getStartChildWorkflowExecutionFailedEventAttributes()?->getCause()
                ?? $event->getSignalExternalWorkflowExecutionFailedEventAttributes()?->getCause()
                ?? $event->getRequestCancelExternalWorkflowExecutionFailedEventAttributes()?->getCause();
            if ($cause !== null) {
                $out .= "Cause: $cause";
                continue;
            }

            $failure = $event->getActivityTaskFailedEventAttributes()?->getFailure()
                ?? $event->getWorkflowTaskFailedEventAttributes()?->getFailure()
                ?? $event->getNexusOperationFailedEventAttributes()?->getFailure()
                ?? $event->getWorkflowExecutionFailedEventAttributes()?->getFailure()
                ?? $event->getChildWorkflowExecutionFailedEventAttributes()?->getFailure()
                ?? $event->getNexusOperationCancelRequestFailedEventAttributes()?->getFailure();

            if ($failure === null) {
                continue;
            }

            $out .= "Failure:\n"
                . "    ========== BEGIN ===========\n"
                . $this->renderFailure($failure, 1)
                . "    =========== END ============";
        }
        return $out;
    }

    private function renderFailure(Failure $failure, int $level): string
    {
        $pad = \str_repeat('    ', $level);
        $fnPad = static fn(string $str): string => $pad . \str_replace("\n", "\n$pad", $str);

        $out = $fnPad('Source: ' . $failure->getSource()) . "\n"
            . $fnPad('Info: ' . $failure->getFailureInfo()) . "\n"
            . $fnPad('Message: ' . $failure->getMessage()) . "\n"
            . $fnPad('Stack trace:') . "\n"
            . $fnPad($failure->getStackTrace()) . "\n";

        $previous = $failure->getCause();
        if ($previous !== null) {
            $out .= $fnPad('————————————————————————————') . "\n"
                . $fnPad('Caused by:') . "\n"
                . $this->renderFailure($previous, $level + 1);
        }
        return $out;
    }
}
