<?php

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use Google\Protobuf\Internal\MapField;
use Google\Protobuf\Internal\Message;
use Google\Protobuf\Internal\RepeatedField;

/**
 * @internall
 */
final class ProtoToArrayConverter
{
    public static function convert(mixed $message): mixed
    {
        if (!$message instanceof Message) {
            return $message;
        }

        // Skip Google Protobuf messages
        if (\str_starts_with($message::class, 'Google\\Protobuf\\')) {
            return $message;
        }

        return \Closure::bind(static function (Message $input): array {
            $result = [];
            $reflection = new \ReflectionClass($input::class);
            foreach ($reflection->getProperties() as $property) {
                $name = $property->getName();
                $value = $input->{$name};

                if ($value instanceof Message) {
                    $result[$name] = ProtoToArrayConverter::convert($value);
                    continue;
                }

                if ($value instanceof RepeatedField || $value instanceof MapField) {
                    $result[$name] = [];
                    foreach ($value as $key => $item) {
                        $result[$name][$key] = ProtoToArrayConverter::convert($item);
                    }
                    continue;
                }

                $result[$name] = $value;
            }

            return $result;
        }, null, $message::class)($message);
    }
}
