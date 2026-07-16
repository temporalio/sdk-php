<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\ChildWorkflowInvocationCache;

use Temporal\Worker\InvocationResult;
use Temporal\Worker\InvocationFailure;
use Temporal\Worker\InvocationMatched;

interface ChildWorkflowInvocationCacheInterface
{
    public function clear(): void;

    /**
     * @param non-empty-string $workflowType
     * @param mixed $value Child workflow result: anything the DataConverter can encode, or an EncodedValues.
     */
    public function saveCompletion(string $workflowType, mixed $value): void;

    /**
     * @param non-empty-string $workflowType
     */
    public function saveFailure(string $workflowType, \Throwable $error): void;

    /**
     * @param non-empty-string $workflowType
     * @param array<array-key, mixed> $args Start arguments to byte-match against, encoded via the DataConverter.
     * @param mixed $value Result returned on a match: anything the DataConverter can encode, or an EncodedValues.
     */
    public function saveCompletionWhen(string $workflowType, array $args, mixed $value): void;

    /**
     * @param non-empty-string $workflowType
     */
    public function has(string $workflowType): bool;

    /**
     * @param non-empty-string $workflowType
     */
    public function get(string $workflowType): InvocationResult|InvocationFailure|InvocationMatched;

    /**
     * @param non-empty-string $workflowType
     */
    public function recordInvoked(string $workflowType): void;

    /**
     * @param non-empty-string $workflowType
     */
    public function wasInvoked(string $workflowType): bool;

    public function saveVersion(string $changeId, int $version): void;

    public function hasVersion(string $changeId): bool;

    public function getVersion(string $changeId): int;

    /**
     * @param mixed $value Side-effect result: anything the DataConverter can encode.
     */
    public function saveSideEffect(mixed $value): void;

    public function hasSideEffect(): bool;

    public function nextSideEffect(): mixed;
}
