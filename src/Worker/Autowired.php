<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

/**
 * @psalm-type FunctionExecutor = \Closure(object|null, array): mixed
 */
class Autowired
{
    /**
     * @var int
     */
    public const SCOPE_OBJECT = 0x01;

    /**
     * @var int
     */
    public const SCOPE_STATIC = 0x02;

    /**
     * @var \ReflectionFunctionAbstract
     */
    private \ReflectionFunctionAbstract $fun;

    /**
     * @psalm-var FunctionExecutor
     * @var \Closure
     */
    private \Closure $executor;

    /**
     * @var int
     */
    private int $scope = 0;

    /**
     * @param \ReflectionFunctionAbstract $fun
     */
    public function __construct(\ReflectionFunctionAbstract $fun)
    {
        $this->fun = $fun;

        $this->boot($fun);
    }

    /**
     * @param int $scope
     * @return bool
     */
    private function scopeMatches(int $scope): bool
    {
        return ($this->scope & $scope) === $scope;
    }

    /**
     * @return bool
     */
    public function isObjectContextRequired(): bool
    {
        return $this->scope === static::SCOPE_OBJECT;
    }

    /**
     * @return bool
     */
    public function isObjectContextAllowed(): bool
    {
        return $this->scopeMatches(static::SCOPE_OBJECT);
    }

    /**
     * @return bool
     */
    public function isStaticContextRequired(): bool
    {
        return $this->scope === static::SCOPE_STATIC;
    }

    /**
     * @return bool
     */
    public function isStaticContextAllowed(): bool
    {
        return $this->scopeMatches(static::SCOPE_STATIC);
    }

    /**
     * @psalm-return FunctionExecutor
     *
     * @param \ReflectionFunctionAbstract $fun
     * @return \Closure
     */
    private function createExecutorFromMethod(\ReflectionMethod $fun): \Closure
    {
        return static function (?object $ctx, array $arguments) use ($fun) {
            try {
                return $fun->invoke($ctx, $arguments);
            } catch (\ReflectionException $e) {
                throw new \BadMethodCallException($e->getMessage(), $e->getCode(), $e);
            }
        };
    }

    /**
     * @psalm-return FunctionExecutor
     *
     * @param \ReflectionFunctionAbstract $fun
     * @return \Closure
     */
    private function createExecutorFromFunction(\ReflectionFunction $fun): \Closure
    {
        return static function (?object $ctx, array $arguments) use ($fun) {
            if ($ctx === null) {
                return $fun->invoke(...$arguments);
            }


            $closure = $fun->getClosure();

            try {
                \set_error_handler(static function (int $type, string $message, string $file, int $line): void {
                    $error = new \ErrorException($message, $type, $type, $file, $line);

                    throw new \BadMethodCallException($message, $type, $error);
                });

                return $closure->call($ctx, ...$arguments);
            } finally {
                \restore_error_handler();
            }
        };
    }

    /**
     * @psalm-return FunctionExecutor
     *
     * @param \ReflectionFunctionAbstract $fun
     * @return \Closure
     */
    private function boot(\ReflectionFunctionAbstract $fun): void
    {
        if ($fun instanceof \ReflectionMethod) {
            $this->executor = $this->createExecutorFromMethod($fun);
            $this->scope = static::SCOPE_OBJECT;

            if ($fun->isStatic()) {
                $this->scope |= static::SCOPE_STATIC;
            }

            return;
        }

        if ($fun instanceof \ReflectionFunction) {
            $this->executor = $this->createExecutorFromFunction($fun);
            $this->scope = static::SCOPE_STATIC;

            if ($fun->isClosure() && $fun->getClosureThis()) {
                $this->scope |= static::SCOPE_OBJECT;
            }

            return;
        }

        throw new \InvalidArgumentException('Unsupported function implementation');
    }

    public function resolve(array $arguments): array
    {
        return [];
    }

    public function getClosureThis()
    {
        return $this->fun->getClosureThis();
    }

    /**
     * @param object|null $ctx
     * @param array $arguments
     * @return mixed
     */
    public function call(?object $ctx, array $arguments)
    {
        $params = $this->resolve($arguments);

        return ($this->executor)($ctx, $params);
    }
}
