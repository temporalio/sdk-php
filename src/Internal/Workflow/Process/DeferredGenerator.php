<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use Temporal\DataConverter\ValuesInterface;

/**
 * A wrapper around a generator that doesn't start the wrapped generator ASAP.
 *
 * @implements \Iterator<mixed, mixed>
 *
 * @internal
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class DeferredGenerator implements \Iterator
{
    private bool $started = false;
    private bool $finished = false;
    private \Generator $generator;

    /** @var array<\Closure(\Throwable): mixed> */
    private array $catchers = [];

    private \Closure $handler;
    private ValuesInterface $values;

    private function __construct() {}

    /**
     * @param \Closure(ValuesInterface): mixed $handler
     */
    public static function fromHandler(\Closure $handler, ValuesInterface $values): self
    {
        $self = new self();
        $self->handler = $handler;
        $self->values = $values;
        return $self;
    }

    /**
     * @param \Generator $generator Started generator.
     */
    public static function fromGenerator(\Generator $generator): self
    {
        $self = new self();
        $self->generator = $generator;
        $self->started = true;
        return $self;
    }

    /**
     * Throw an exception into the generator.
     *
     * @note doesn't throw generator's exceptions; use {@see catch()} to handle them.
     */
    public function throw(\Throwable $exception): void
    {
        $this->started or throw new \LogicException('Cannot throw exception into a generator that was not started.');
        $this->finished and throw new \LogicException(
            'Cannot throw exception into a generator that was already finished.',
        );
        try {
            $this->generator->throw($exception);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Send a value to the generator.
     *
     * @note doesn't throw generator's exceptions; use {@see catch()} to handle them.
     */
    public function send(mixed $value): mixed
    {
        $this->start();
        $this->finished and throw new \LogicException('Cannot send value to a generator that was already finished.');
        try {
            return $this->generator->send($value);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get the return value of the generator if it was finished.
     */
    public function getReturn(): mixed
    {
        // $this->start();
        try {
            return $this->generator->getReturn();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get the current value of the generator.
     */
    public function current(): mixed
    {
        $this->start();
        try {
            return $this->generator->current();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get the current key of the generator.
     */
    public function key(): mixed
    {
        $this->start();
        try {
            return $this->generator->key();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Start or resume the generator.
     */
    public function next(): void
    {
        if (!$this->started || $this->finished) {
            $this->finished or $this->start();
            return;
        }

        try {
            $this->generator->next();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Check if the generator is not finished.
     *
     * @note It starts the Generator.
     */
    public function valid(): bool
    {
        $this->start();
        try {
            return $this->generator->valid();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function rewind(): void
    {
        $this->generator->rewind();
    }

    /**
     * Add an exception handler.
     *
     * @param \Closure(\Throwable): mixed $handler
     */
    public function catch(callable $handler): self
    {
        $this->catchers[] = $handler;
        return $this;
    }

    private static function getDummyGenerator(): \Generator
    {
        static $generator;

        if ($generator === null) {
            $generator = (static function (): \Generator {
                yield;
            })();
            $generator->current();
        }

        return $generator;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        try {
            $result = ($this->handler)($this->values);

            if ($result instanceof \Generator) {
                $this->generator = $result;
                return;
            }

            /** @psalm-suppress all */
            $this->generator = (static function (mixed $result): \Generator {
                return $result;
                yield;
            })($result);
            $this->finished = true;
        } catch (\Throwable $e) {
            $this->generator = self::getDummyGenerator();
            $this->handleException($e);
        } finally {
            unset($this->handler, $this->values);
        }
    }

    private function handleException(\Throwable $e): never
    {
        $this->finished and throw $e;
        $this->finished = true;
        foreach ($this->catchers as $catch) {
            try {
                $catch($e);
            } catch (\Throwable) {
                // Do nothing.
            }
        }

        $this->catchers = [];
        throw $e;
    }
}
