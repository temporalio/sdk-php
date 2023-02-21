<?php

declare(strict_types=1);

namespace Temporal\Interceptor;

use Closure;

/**
 * @template TMiddleware of object
 * @template TReturn of mixed
 *
 * @psalm-type TLast = callable(mixed ...): mixed
 *
 * @internal
 */
final class Pipeline
{
    /** @var non-empty-string */
    private string $method;

    /** @var Closure */
    private Closure $last;

    /** @var list<TMiddleware> */
    private array $middlewares = [];
    /** @var int<0, max> Current middleware key */
    private int $current = 0;

    /**
     * @param iterable<TMiddleware> $middlewares
     */
    private function __construct(
        iterable $middlewares,
    ) {
        // Reset keys
        foreach ($middlewares as $middleware) {
            $this->middlewares[] = $middleware;
        }
    }

    public static function prepare(array $interceptors)
    {
        return new self($interceptors);
    }

    /**
     * @param non-empty-string $method
     * @param Closure $last
     * @param mixed ...$arguments
     *
     * @return TReturn
     */
    public function execute(
        string $method,
        Closure $last,
        mixed ...$arguments,
    ): mixed {
        $this->method = $method;
        $this->last = $last;

        return $this->__invoke(...$arguments);
    }

    public function __invoke(mixed ...$arguments): mixed
    {
        $middleware = $this->middlewares[$this->current] ?? null;

        if ($middleware === null) {
            return ($this->last)(...$arguments);
        }

        $next = $this->next();
        $arguments[] = $next;
        return $middleware->{$this->method}(...$arguments);
    }

    private function next(): self
    {
        $new = clone $this;
        ++$new->current;

        return $new;
    }
}
