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
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;

final class RoadRunnerChildWorkflowInvocationCache implements ChildWorkflowInvocationCacheInterface
{
    private const CACHE_NAME = 'test';
    private const KEY_PREFIX = 'childWorkflow.';
    private const INVOKED_PREFIX = 'childWorkflowInvoked.';
    private const VERSION_PREFIX = 'workflowVersion.';
    private const SIDE_EFFECT_LIST = 'sideEffect.list';
    private const SIDE_EFFECT_CURSOR = 'sideEffect.cursor';

    private StorageInterface $cache;
    private DataConverterInterface $dataConverter;

    public function __construct(string $host, string $cacheName, ?DataConverterInterface $dataConverter = null)
    {
        $this->cache = (new Factory(RPC::create($host)))->select($cacheName);
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();
    }

    public static function create(?DataConverterInterface $dataConverter = null): self
    {
        $env = Environment::fromGlobals();
        return new self($env->getRPCAddress(), self::CACHE_NAME, $dataConverter);
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function saveCompletion(string $workflowType, mixed $value): void
    {
        $this->cache->set(
            self::KEY_PREFIX . $workflowType,
            InvocationResult::fromValue($value, $this->dataConverter),
        );
    }

    public function saveFailure(string $workflowType, \Throwable $error): void
    {
        $this->cache->set(
            self::KEY_PREFIX . $workflowType,
            InvocationFailure::fromThrowable($error, $this->dataConverter),
        );
    }

    public function saveCompletionWhen(string $workflowType, array $args, mixed $value): void
    {
        $key = self::KEY_PREFIX . $workflowType;
        $matched = $this->cache->has($key) ? $this->cache->get($key) : null;
        if (!$matched instanceof InvocationMatched) {
            $matched = new InvocationMatched();
        }

        $matched->addCase(
            EncodedValues::fromValues($args, $this->dataConverter)->toPayloads(),
            InvocationResult::fromValue($value, $this->dataConverter),
        );

        $this->cache->set($key, $matched);
    }

    public function has(string $workflowType): bool
    {
        return $this->cache->has(self::KEY_PREFIX . $workflowType);
    }

    public function get(string $workflowType): InvocationResult|InvocationFailure|InvocationMatched
    {
        $key = self::KEY_PREFIX . $workflowType;
        if (!$this->cache->has($key)) {
            throw new \LogicException(\sprintf('No mock stored for child workflow "%s"', $workflowType));
        }

        return $this->cache->get($key);
    }

    public function recordInvoked(string $workflowType): void
    {
        $this->cache->set(self::INVOKED_PREFIX . $workflowType, true);
    }

    public function wasInvoked(string $workflowType): bool
    {
        return $this->cache->has(self::INVOKED_PREFIX . $workflowType);
    }

    public function saveVersion(string $changeId, int $version): void
    {
        $this->cache->set(self::VERSION_PREFIX . $changeId, $version);
    }

    public function hasVersion(string $changeId): bool
    {
        return $this->cache->has(self::VERSION_PREFIX . $changeId);
    }

    public function getVersion(string $changeId): int
    {
        return (int) $this->cache->get(self::VERSION_PREFIX . $changeId);
    }

    public function saveSideEffect(mixed $value): void
    {
        $list = $this->cache->has(self::SIDE_EFFECT_LIST) ? $this->cache->get(self::SIDE_EFFECT_LIST) : [];
        $list[] = InvocationResult::fromValue($value, $this->dataConverter);
        $this->cache->set(self::SIDE_EFFECT_LIST, $list);
    }

    public function hasSideEffect(): bool
    {
        if (!$this->cache->has(self::SIDE_EFFECT_LIST)) {
            return false;
        }

        return $this->sideEffectCursor() < \count($this->cache->get(self::SIDE_EFFECT_LIST));
    }

    public function nextSideEffect(): mixed
    {
        /** @var list<InvocationResult> $list */
        $list = $this->cache->get(self::SIDE_EFFECT_LIST);
        $cursor = $this->sideEffectCursor();
        $index = \min($cursor, \count($list) - 1);
        $this->cache->set(self::SIDE_EFFECT_CURSOR, $cursor + 1);

        return $list[$index]->toValue(null, $this->dataConverter);
    }

    private function sideEffectCursor(): int
    {
        return $this->cache->has(self::SIDE_EFFECT_CURSOR) ? (int) $this->cache->get(self::SIDE_EFFECT_CURSOR) : 0;
    }
}
