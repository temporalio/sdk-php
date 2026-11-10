<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Google\Protobuf\Descriptor;
use Google\Protobuf\DescriptorPool;
use Google\Protobuf\FieldDescriptor;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\Message;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\SearchAttributes;

/**
 * @internal
 */
final class PayloadWalker
{
    /** @var array<string, list<array{non-empty-string, bool, Descriptor}>> */
    private static array $plans = [];

    /** @var array<string, bool> */
    private static array $reachable = [];

    /**
     * @return list<Payload>
     */
    public static function collect(Message $message): array
    {
        $payloads = [];
        self::walk($message, DescriptorPool::getGeneratedPool()->getDescriptorByClassName($message::class), $payloads);

        return $payloads;
    }

    /**
     * @param list<Payload> $payloads
     */
    private static function walk(Message $message, Descriptor $descriptor, array &$payloads): void
    {
        if ($message instanceof Payload) {
            $payloads[] = $message;
            return;
        }

        foreach (self::plan($descriptor) as [$getter, $repeated, $child]) {
            /** @var null|Message|iterable<Message> $value */
            $value = $message->{$getter}();
            if (!$repeated) {
                if ($value instanceof Message) {
                    self::walk($value, $child, $payloads);
                }
                continue;
            }

            /** @var iterable<Message> $value */
            foreach ($value as $item) {
                self::walk($item, $child, $payloads);
            }
        }
    }

    /**
     * @return list<array{non-empty-string, bool, Descriptor}>
     */
    private static function plan(Descriptor $descriptor): array
    {
        $name = $descriptor->getFullName();
        if (isset(self::$plans[$name])) {
            return self::$plans[$name];
        }

        $plan = [];
        for ($i = 0; $i < $descriptor->getFieldCount(); $i++) {
            $field = $descriptor->getField($i);
            $child = self::messageType($field);
            if ($child === null || !self::reachesPayload($child)) {
                continue;
            }

            $plan[] = [
                'get' . \str_replace('_', '', \ucwords($field->getName(), '_')),
                $field->isRepeated(),
                $child,
            ];
        }

        return self::$plans[$name] = $plan;
    }

    private static function messageType(FieldDescriptor $field): ?Descriptor
    {
        if ($field->getType() !== GPBType::MESSAGE) {
            return null;
        }

        $type = $field->getMessageType();
        if ($field->isMap()) {
            return self::messageType($type->getField(1));
        }

        return $type->getClass() === SearchAttributes::class ? null : $type;
    }

    private static function reachesPayload(Descriptor $descriptor): bool
    {
        $name = $descriptor->getFullName();
        if (isset(self::$reachable[$name])) {
            return self::$reachable[$name];
        }

        self::$reachable[$name] = true;
        if ($descriptor->getClass() === Payload::class) {
            return true;
        }

        for ($i = 0; $i < $descriptor->getFieldCount(); $i++) {
            $child = self::messageType($descriptor->getField($i));
            if ($child !== null && self::reachesPayload($child)) {
                return true;
            }
        }

        return self::$reachable[$name] = false;
    }
}
