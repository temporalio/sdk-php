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
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

final class InMemoryActivityInvocationCache implements ActivityInvocationCacheInterface
{
    /**
     * @var array<non-empty-string, InvocationFailure|InvocationResult|InvocationResultQueue|InvocationMatched>
     */
    private array $cache = [];

    private DataConverterInterface $dataConverter;

    public function __construct(?DataConverterInterface $dataConverter = null)
    {
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();
    }

    public function clear(): void
    {
        $this->cache = [];
    }

    public function saveCompletion(string $activityMethodName, mixed $value): void
    {
        $this->cache[$activityMethodName] = InvocationResult::fromValue($value, $this->dataConverter);
    }

    public function saveFailure(string $activityMethodName, \Throwable $error): void
    {
        $this->cache[$activityMethodName] = InvocationFailure::fromThrowable($error, $this->dataConverter);
    }

    public function saveConsecutiveCompletions(string $activityMethodName, array $values): void
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = InvocationResult::fromValue($value, $this->dataConverter);
        }

        $this->cache[$activityMethodName] = new InvocationResultQueue($items);
    }

    public function saveCompletionWhen(string $activityMethodName, array $args, mixed $value): void
    {
        $matched = $this->cache[$activityMethodName] ?? null;
        if (!$matched instanceof InvocationMatched) {
            $matched = new InvocationMatched();
        }

        $matched->addCase(
            EncodedValues::fromValues($args, $this->dataConverter)->toPayloads(),
            InvocationResult::fromValue($value, $this->dataConverter),
        );

        $this->cache[$activityMethodName] = $matched;
    }

    public function canHandle(ServerRequestInterface $request): bool
    {
        if (!\in_array($request->getName(), ['InvokeActivity', 'InvokeLocalActivity'], true)) {
            return false;
        }

        $activityMethodName = $request->getOptions()['name'] ?? '';

        return isset($this->cache[$activityMethodName]);
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $activityMethodName = $request->getOptions()['name'];
        $value = $this->cache[$activityMethodName];

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
            $current = $value->current();
            $value->advance();
            $value = $current;
        }

        return $value instanceof InvocationFailure
            ? reject($value->toThrowable($this->dataConverter))
            : resolve($value->toEncodedValues($this->dataConverter));
    }
}
