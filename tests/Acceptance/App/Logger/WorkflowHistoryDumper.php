<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Logger;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

final class WorkflowHistoryDumper
{
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
}
