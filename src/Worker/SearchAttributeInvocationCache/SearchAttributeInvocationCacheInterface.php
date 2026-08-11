<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\SearchAttributeInvocationCache;

interface SearchAttributeInvocationCacheInterface
{
    public const OPERATION_SET = 'set';
    public const OPERATION_UNSET = 'unset';

    public function clear(): void;

    /**
     * @param non-empty-string $name
     * @param array{operation: string, type: string, value?: mixed} $entry
     */
    public function recordUpsert(string $name, array $entry): void;

    /**
     * @param non-empty-string $name
     */
    public function wasUpserted(string $name): bool;

    /**
     * @param non-empty-string $name
     * @return array{operation: string, type: string, value?: mixed}|null
     */
    public function getUpsert(string $name): ?array;

    /**
     * @return array<string, array{operation: string, type: string, value?: mixed}>
     */
    public function all(): array;
}
