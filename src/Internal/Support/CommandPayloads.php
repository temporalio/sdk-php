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
 * @internal
 */
final class CommandPayloads
{
    /**
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
            if ($command->getName() === ExecuteLocalActivity::NAME) {
                return ['payloads' => 0, 'memo' => 0];
            }

            $options = $command->getOptions();

            if ($withPayloads) {
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

    private static function mapSize(mixed $fields, DataConverterInterface $converter): int
    {
        $size = 0;
        foreach (self::fieldsOf($fields) as $key => $value) {
            $size += \strlen((string) $key) + \strlen($converter->toPayload($value)->getData());
        }

        return $size;
    }

    /**
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
