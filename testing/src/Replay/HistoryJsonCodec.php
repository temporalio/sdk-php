<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Google\Protobuf\Descriptor;
use Google\Protobuf\DescriptorPool;
use Google\Protobuf\EnumDescriptor;
use Google\Protobuf\Internal\GPBType;
use Temporal\Api\History\V1\History;

final class HistoryJsonCodec
{
    private const UNSPECIFIED_SUFFIX = 'UNSPECIFIED';

    public static function decode(string $json, int $lastEventId = 0): History
    {
        $data = \json_decode($json, flags: \JSON_THROW_ON_ERROR);
        self::normalizeEnums($data, DescriptorPool::getGeneratedPool()->getDescriptorByClassName(History::class));

        $history = new History();
        $history->mergeFromJsonString(\json_encode($data, \JSON_THROW_ON_ERROR), true);

        $events = [];
        foreach ($history->getEvents() as $event) {
            $events[] = $event;
            if ($event->getEventId() === $lastEventId) {
                break;
            }
        }
        $history->setEvents($events);

        return $history;
    }

    private static function normalizeEnums(\stdClass $message, Descriptor $descriptor): void
    {
        for ($i = 0; $i < $descriptor->getFieldCount(); ++$i) {
            $field = $descriptor->getField($i);
            $key = \lcfirst(\str_replace('_', '', \ucwords($field->getName(), '_')));
            if (!isset($message->{$key})) {
                $key = $field->getName();
            }
            if (!isset($message->{$key}) || $field->isMap()) {
                continue;
            }

            $values = $field->isRepeated() ? $message->{$key} : [$message->{$key}];
            if ($field->getType() === GPBType::MESSAGE) {
                foreach ($values as $value) {
                    if ($value instanceof \stdClass) {
                        self::normalizeEnums($value, $field->getMessageType());
                    }
                }
            }

            if ($field->getType() === GPBType::ENUM) {
                $names = \array_map(static fn(mixed $value): mixed => \is_string($value) ? self::enumName($field->getEnumType(), $value) : $value, $values);
                $message->{$key} = $field->isRepeated() ? $names : $names[0];
            }
        }
    }

    private static function enumName(EnumDescriptor $enum, string $value): string
    {
        $prefix = \substr($enum->getValue(0)->getName(), 0, -\strlen(self::UNSPECIFIED_SUFFIX));
        $name = \strtoupper((string) \preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value));

        return \str_starts_with($name, $prefix) ? $name : $prefix . $name;
    }
}
