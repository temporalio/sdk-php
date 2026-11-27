<?php

declare(strict_types=1);

namespace Temporal\Internal\Promise;

use React\Promise\Internal\CancellationQueue as ReactCancellationQueue;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Temporal\Internal\Transport\CompletableResult;
use Temporal\Promise as TemporalPromise;

use function React\Promise\all;

/**
 * @internal
 * @psalm-internal Temporal\Internal
 * @implements PromiseInterface<mixed>
 */
final class Abandoned implements PromiseInterface
{
    private static ?\WeakMap $dissolved = null;

    public function __construct(
        private readonly bool $forward = false,
    ) {}

    public static function dissolve(PromiseInterface $promise): void
    {
        $outermost = self::$dissolved === null;
        self::$dissolved ??= new \WeakMap();

        try {
            self::dissolveGraph($promise, self::$dissolved);
        } finally {
            if ($outermost) {
                self::$dissolved = null;
            }
        }
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        if ($this->forward) {
            if ($onFulfilled !== null) {
                $onFulfilled(new self());
            }

            return new self();
        }

        $resolve = self::resolverOf($onFulfilled);
        if ($resolve !== null) {
            $target = (new \ReflectionFunction($resolve))->getStaticVariables()['target'] ?? null;
            if ($target instanceof PromiseInterface) {
                self::dissolve($target);
            }

            $resolve(new self());
        }

        return new self(forward: true);
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this;
    }

    public function cancel(): void {}

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this;
    }

    private static function dissolveGraph(PromiseInterface $promise, \WeakMap $dissolved): void
    {
        $pending = [$promise];

        while ($pending !== []) {
            $promise = \array_pop($pending);
            if (!$promise instanceof Promise || isset($dissolved[$promise])) {
                continue;
            }

            $dissolved[$promise] = true;
            [$result, $handlers, $canceller] = self::stateOf($promise);

            if ($result !== null) {
                continue;
            }

            foreach ($handlers as $handler) {
                $handler(new self());
            }

            \array_push($pending, ...self::dependenciesOf($canceller));
        }
    }

    private static function stateOf(Promise $promise): array
    {
        return \array_map(
            static fn(string $name): mixed => (new \ReflectionProperty(Promise::class, $name))->getValue($promise),
            ['result', 'handlers', 'canceller'],
        );
    }

    /**
     * @return list<mixed>
     */
    private static function dependenciesOf(?callable $canceller): array
    {
        if ($canceller instanceof \Closure) {
            $function = new \ReflectionFunction($canceller);

            return $function->getClosureScopeClass()?->getName() === Promise::class
                ? [$function->getStaticVariables()['parent'] ?? null]
                : [];
        }

        if ($canceller instanceof CancellationQueue || $canceller instanceof ReactCancellationQueue) {
            return \array_values((new \ReflectionProperty($canceller, 'queue'))->getValue($canceller));
        }

        return [];
    }

    private static function resolverOf(?callable $callback): ?\Closure
    {
        if (!$callback instanceof \Closure) {
            return null;
        }

        $function = new \ReflectionFunction($callback);
        $variables = $function->getStaticVariables();
        $scope = $function->getClosureScopeClass()?->getName();

        if ($scope === Promise::class
            && ($variables['target'] ?? null) instanceof PromiseInterface
            && ($function->getParameters()[0] ?? null)?->getName() === 'value'
        ) {
            return $callback;
        }

        if ($scope === CompletableResult::class) {
            return self::resolverOf($variables['callback'] ?? null);
        }

        if ($scope === TemporalPromise::class
            || $function->getFileName() === (new \ReflectionFunction(all(...)))->getFileName()
        ) {
            return self::resolverOf($variables['resolve'] ?? null);
        }

        return null;
    }
}
