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
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;

final class InMemoryChildWorkflowInvocationCache implements ChildWorkflowInvocationCacheInterface
{
    /**
     * @var array<non-empty-string, InvocationFailure|InvocationResult|InvocationMatched>
     */
    private array $cache = [];

    /** @var array<string, int> */
    private array $versions = [];

    /** @var array<string, true> */
    private array $invoked = [];

    /** @var list<InvocationResult> */
    private array $sideEffects = [];

    private int $sideEffectCursor = 0;
    private DataConverterInterface $dataConverter;

    public function __construct(?DataConverterInterface $dataConverter = null)
    {
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->versions = [];
        $this->invoked = [];
        $this->sideEffects = [];
        $this->sideEffectCursor = 0;
    }

    public function recordInvoked(string $workflowType): void
    {
        $this->invoked[$workflowType] = true;
    }

    public function wasInvoked(string $workflowType): bool
    {
        return isset($this->invoked[$workflowType]);
    }

    public function saveCompletion(string $workflowType, mixed $value): void
    {
        $this->cache[$workflowType] = InvocationResult::fromValue($value, $this->dataConverter);
    }

    public function saveFailure(string $workflowType, \Throwable $error): void
    {
        $this->cache[$workflowType] = InvocationFailure::fromThrowable($error, $this->dataConverter);
    }

    public function saveCompletionWhen(string $workflowType, array $args, mixed $value): void
    {
        $matched = $this->cache[$workflowType] ?? null;
        if (!$matched instanceof InvocationMatched) {
            $matched = new InvocationMatched();
        }

        $matched->addCase(
            EncodedValues::fromValues($args, $this->dataConverter)->toPayloads(),
            InvocationResult::fromValue($value, $this->dataConverter),
        );

        $this->cache[$workflowType] = $matched;
    }

    public function has(string $workflowType): bool
    {
        return isset($this->cache[$workflowType]);
    }

    public function get(string $workflowType): InvocationResult|InvocationFailure|InvocationMatched
    {
        if (!isset($this->cache[$workflowType])) {
            throw new \LogicException(\sprintf('No mock stored for child workflow "%s"', $workflowType));
        }

        return $this->cache[$workflowType];
    }

    public function saveVersion(string $changeId, int $version): void
    {
        $this->versions[$changeId] = $version;
    }

    public function hasVersion(string $changeId): bool
    {
        return isset($this->versions[$changeId]);
    }

    public function getVersion(string $changeId): int
    {
        return $this->versions[$changeId];
    }

    public function saveSideEffect(mixed $value): void
    {
        $this->sideEffects[] = InvocationResult::fromValue($value, $this->dataConverter);
    }

    public function hasSideEffect(): bool
    {
        return $this->sideEffectCursor < \count($this->sideEffects);
    }

    public function nextSideEffect(): mixed
    {
        $index = \min($this->sideEffectCursor, \count($this->sideEffects) - 1);
        ++$this->sideEffectCursor;

        return $this->sideEffects[$index]->toValue(null, $this->dataConverter);
    }
}
