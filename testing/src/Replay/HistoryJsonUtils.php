<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

/**
 * Helper methods supporting transformation of History's "Proto Json" compatible format, which is
 * supported by {@link com.google.protobuf.util.JsonFormat} to the format of Temporal history
 * supported by tctl and back.
 */
final class HistoryJsonUtils
{
    private const ENUM_CONVERSION_POLICY = [
        'eventType' => 'EVENT_TYPE_',
        'kind' => 'TASK_QUEUE_KIND_',
        'parentClosePolicy' => 'PARENT_CLOSE_POLICY_',
        'workflowIdReusePolicy' => 'WORKFLOW_ID_REUSE_POLICY_',
        'initiator' => 'CONTINUE_AS_NEW_INITIATOR_',
        'retryState' => 'RETRY_STATE_',
    ];

    public function prepareEnums(string $json): string
    {
        $data = \json_decode(
            $json,
            true,
            512,
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION
        );
        foreach ($data['events'] as &$eventData) {
            $this->convertEnum($eventData);
        }

        return json_encode($data);
    }

    private function convertEnum(array &$record): void
    {
        foreach ($record as $field => $value) {
            if (is_array($value)) {
                $this->convertEnum($value);
                continue;
            }

            if (isset(self::ENUM_CONVERSION_POLICY[$field])) {
                $record[$field] = self::ENUM_CONVERSION_POLICY[$field] . strtoupper(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $value));
            }
        }
    }
}
