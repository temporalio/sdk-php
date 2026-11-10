<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Temporal\DataConverter\ExternalStorage\StorageDriverActivityInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverTargetInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;

/**
 * @internal
 */
final class ObjectKey
{
    public const VERSION = 'v0';
    public const NULL_SEGMENT = 'null';

    /**
     * @param \Closure(string): string $encode
     */
    public static function build(?StorageDriverTargetInfo $target, string $digest, \Closure $encode): string
    {
        $digestSegment = self::digestSegment($digest);

        return match (true) {
            $target instanceof StorageDriverWorkflowInfo => self::VERSION
                . '/ns/' . $encode($target->namespace)
                . '/wt/' . $encode($target->workflowType)
                . '/wi/' . $encode($target->workflowId)
                . '/ri/' . $encode($target->runId)
                . $digestSegment,
            $target instanceof StorageDriverActivityInfo => self::VERSION
                . '/ns/' . $encode($target->namespace)
                . '/at/' . $encode($target->activityType)
                . '/ai/' . $encode($target->activityId)
                . '/ri/' . $encode($target->runId)
                . $digestSegment,
            default => self::VERSION . $digestSegment,
        };
    }

    public static function digestSegment(string $digest): string
    {
        return '/d/' . StoredObject::HASH_ALGORITHM . '/' . $digest;
    }

    /**
     * @param non-empty-string $unsafePattern
     */
    public static function encodeBytes(string $value, string $unsafePattern): string
    {
        if ($value === '') {
            return self::NULL_SEGMENT;
        }

        return (string) \preg_replace_callback(
            $unsafePattern,
            static fn(array $match): string => \strtoupper(\implode('', \array_map(
                static fn(string $byte): string => '%' . \bin2hex($byte),
                \str_split($match[0]),
            ))),
            $value,
        );
    }
}
