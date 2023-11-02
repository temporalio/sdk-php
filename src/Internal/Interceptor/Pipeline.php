<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Interceptor;

use Closure;

/**
 * Pipeline is a processor for interceptors chain.
 *
 * @template TInterceptor of object
 * @template TReturn of mixed
 *
 * @psalm-type TLast = Closure(mixed ...): mixed
 * @psalm-type TCallable = callable(mixed ...): mixed
 *
 * @psalm-immutable
 * @internal
 */
final class Pipeline
{
    /** @var non-empty-string */
    private string $method;

    /** @var Closure */
    private Closure $last;

    /** @var list<TInterceptor> */
    private array $interceptors = [];
    /** @var int<0, max> Current interceptor key */
    private int $current = 0;

    /**
     * @param iterable<TInterceptor> $interceptors
     */
    private function __construct(
        iterable $interceptors,
    ) {
        // Reset keys
        foreach ($interceptors as $interceptor) {
            $this->interceptors[] = $interceptor;
        }
    }

    /**
     * Make sure that interceptors implement the same interface.
     *
     * @template T of Interceptor
     *
     * @param iterable<T> $interceptors
     *
     * @return self<T, mixed>
     */
    public static function prepare(iterable $interceptors): self
    {
        return new self($interceptors);
    }

    /**
     * @param Closure $last
     * @param non-empty-string $method Method name of the all interceptors.
     *
     * @return TCallable
     */
    public function with(\Closure $last, string $method): callable
    {
        $new = clone $this;

        $new->last = $last;
        $new->method = $method;

        return $new;
    }

    /**
     * Must be used after {@see with()} method.
     *
     * @param mixed ...$arguments
     *
     * @return TReturn
     */
    public function __invoke(mixed ...$arguments): mixed
    {
        $interceptor = $this->interceptors[$this->current] ?? null;

        if ($interceptor === null) {
            return ($this->last)(...$arguments);
        }

        $next = $this->next();
        $arguments[] = $next;

        return $interceptor->{$this->method}(...$arguments);
    }

    private function next(): self
    {
        $new = clone $this;
        ++$new->current;

        return $new;
    }
}
