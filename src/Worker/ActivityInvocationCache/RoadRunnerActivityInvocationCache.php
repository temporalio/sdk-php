<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use Temporal\Worker\InvocationResult;
use Temporal\Worker\InvocationFailure;
use Temporal\Worker\InvocationMatched;
use Temporal\Worker\InvocationResultQueue;
use React\Promise\PromiseInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

final class RoadRunnerActivityInvocationCache implements ActivityInvocationCacheInterface
{
    private const CACHE_NAME = 'test';

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

    public function saveCompletion(string $activityMethodName, mixed $value): void
    {
        $this->cache->set($activityMethodName, InvocationResult::fromValue($value, $this->dataConverter));
    }

    public function saveFailure(string $activityMethodName, \Throwable $error): void
    {
        $this->cache->set($activityMethodName, InvocationFailure::fromThrowable($error, $this->dataConverter));
    }

    public function saveConsecutiveCompletions(string $activityMethodName, array $values): void
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = InvocationResult::fromValue($value, $this->dataConverter);
        }

        $this->cache->set($activityMethodName, new InvocationResultQueue($items));
    }

    public function saveCompletionWhen(string $activityMethodName, array $args, mixed $value): void
    {
        $matched = $this->cache->get($activityMethodName);
        if (!$matched instanceof InvocationMatched) {
            $matched = new InvocationMatched();
        }

        $matched->addCase(
            EncodedValues::fromValues($args, $this->dataConverter)->toPayloads(),
            InvocationResult::fromValue($value, $this->dataConverter),
        );

        $this->cache->set($activityMethodName, $matched);
    }

    public function canHandle(ServerRequestInterface $request): bool
    {
        if (!\in_array($request->getName(), ['InvokeActivity', 'InvokeLocalActivity'], true)) {
            return false;
        }

        $activityMethodName = $request->getOptions()['name'] ?? '';

        return $this->cache->has($activityMethodName);
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $activityMethodName = $request->getOptions()['name'];
        $value = $this->cache->get($activityMethodName);

        if ($value instanceof InvocationMatched) {
            $matched = $value->match($request->getPayloads()->toPayloads());
            if ($matched === null) {
                return reject(new InvalidArgumentException(
                    \sprintf('No matching expectation for activity "%s"', $activityMethodName),
                ));
            }
            $value = $matched;
        }

        if ($value instanceof InvocationResultQueue) {
            $item = $value->current();
            $value->advance();
            $this->cache->set($activityMethodName, $value);
            $value = $item;
        }

        if ($value instanceof InvocationFailure) {
            return reject($value->toThrowable($this->dataConverter));
        }

        if ($value instanceof InvocationResult) {
            return resolve($value->toEncodedValues($this->dataConverter));
        }

        return reject(new InvalidArgumentException('Invalid cache value'));
    }
}
