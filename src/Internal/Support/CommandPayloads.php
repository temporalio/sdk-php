<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Temporal\Api\Common\V1\Memo;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Transport\Request\ExecuteLocalActivity;
use Temporal\Internal\Transport\Request\UpsertMemo;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;
use Temporal\Internal\Transport\Request\UpsertTypedSearchAttributes;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * Sizes the server measures in a command a Worker sends.
 *
 * Both the warning and the error limits look at the same values, so the knowledge of where the
 * payloads of a command live stays here.
 *
 * @internal
 */
final class CommandPayloads
{
    /**
     * Payload sizes of a command, by the limit they are measured against.
     *
     * Converting a value costs as much as sending it, so a size nobody is going to read is not
     * measured at all.
     *
     * @param bool $withPayloads Whether the payload size is needed.
     * @param bool $withMemo Whether the memo size is needed.
     *
     * @return array{payloads: int, memo: int}
     */
    public static function sizes(
        CommandInterface $command,
        DataConverterInterface $converter,
        bool $withPayloads = true,
        bool $withMemo = true,
    ): array {
        $payloads = 0;
        $memo = 0;

        if ($command instanceof RequestInterface) {
            // Local Activity arguments are not sent to the server
            if ($command->getName() === ExecuteLocalActivity::NAME) {
                return ['payloads' => 0, 'memo' => 0];
            }

            $options = $command->getOptions();

            if ($withPayloads) {
                // Memo and Search Attribute upserts are maps measured key by key against the
                // payload limit, the way the server measures them
                $fields = match ($command->getName()) {
                    UpsertMemo::NAME => $options['memo'] ?? null,
                    UpsertSearchAttributes::NAME => $options['searchAttributes'] ?? null,
                    UpsertTypedSearchAttributes::NAME => self::valuesOf($options['search_attributes'] ?? null),
                    default => null,
                };

                if ($fields !== null) {
                    $payloads += self::mapSize($fields, $converter);
                }

                $payloads += self::valuesSize($command->getPayloads(), $converter);
            }

            // An upserted Memo is measured against the memo limit as well, as the server does,
            // and a Child Workflow carries a Memo of its own
            if ($withMemo) {
                $memo += self::memoSize(match ($command->getName()) {
                    default => $options['options']['Memo'] ?? null,
                    UpsertMemo::NAME => $options['memo'] ?? null,
                }, $converter);
            }
        }

        return ['payloads' => $payloads, 'memo' => $memo];
    }

    public static function valuesSize(ValuesInterface $values, DataConverterInterface $converter): int
    {
        if ($values->count() === 0) {
            return 0;
        }

        $values->setDataConverter($converter);

        return \strlen($values->toPayloads()->serializeToString());
    }

    /**
     * @param mixed $fields Raw values of a Memo, not converted yet.
     */
    private static function memoSize(mixed $fields, DataConverterInterface $converter): int
    {
        $fields = self::fieldsOf($fields);
        if ($fields === []) {
            return 0;
        }

        $payloads = [];
        foreach ($fields as $key => $value) {
            $payloads[(string) $key] = $converter->toPayload($value);
        }

        return \strlen((new Memo())->setFields($payloads)->serializeToString());
    }

    /**
     * Size of a map of payloads: the server sums the key lengths with the sizes of the payload
     * data, so the encoding overhead of the map itself is not counted.
     *
     * The values are converted with the Workflow's own converter, while the one RoadRunner uses
     * produces the bytes that actually reach the server, so the size is an estimate.
     *
     * @param mixed $fields Raw values of the map, not converted yet.
     */
    private static function mapSize(mixed $fields, DataConverterInterface $converter): int
    {
        $size = 0;
        foreach (self::fieldsOf($fields) as $key => $value) {
            $size += \strlen((string) $key) + \strlen($converter->toPayload($value)->getData());
        }

        return $size;
    }

    /**
     * Values of a typed Search Attribute update, which carries the type and the operation
     * next to the value itself. An `unset` update has no value to measure.
     *
     * @return array<array-key, mixed>
     */
    private static function valuesOf(mixed $fields): array
    {
        $result = [];
        foreach (self::fieldsOf($fields) as $key => $update) {
            if (\is_array($update) && \array_key_exists('value', $update)) {
                $result[$key] = $update['value'];
            }
        }

        return $result;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function fieldsOf(mixed $fields): array
    {
        return match (true) {
            $fields instanceof \stdClass => (array) $fields,
            \is_array($fields) => $fields,
            default => [],
        };
    }
}
