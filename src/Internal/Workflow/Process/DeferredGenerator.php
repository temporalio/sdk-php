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
    private mixed $key = null;
    private mixed $value = null;
    private mixed $result = null;
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
        $self->fill();
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
            $this->fill();
        } catch (\Throwable $e) {
            $this->handleException($e);
            throw $e;
        }
    }

    /**
     * Send a value to the generator.
     *
     * @note doesn't throw generator's exceptions; use {@see catch()} to handle them.
     */
    public function send(mixed $value): mixed
    {
        $this->started or throw new \LogicException('Cannot send value to a generator that was not started.');
        $this->finished and throw new \LogicException('Cannot send value to a generator that was already finished.');
        try {
            $result = $this->generator->send($value);
            $this->fill();
            return $result;
        } catch (\Throwable $e) {
            $this->handleException($e);
            throw $e;
        }
    }

    /**
     * Get the return value of the generator if it was finished.
     */
    public function getReturn(): mixed
    {
        $this->finished or throw new \LogicException('Cannot get return value of a generator that was not finished.');
        return $this->result;
    }

    /**
     * Get the current value of the generator.
     */
    public function current(): mixed
    {
        $this->start();
        return $this->value;
    }

    /**
     * Get the current key of the generator.
     */
    public function key(): mixed
    {
        $this->start();
        return $this->key;
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
            $this->fill();
        } catch (\Throwable $e) {
            $this->handleException($e);
            throw $e;
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
        return !$this->finished;
    }

    public function rewind(): void
    {
        $this->started and throw new \LogicException('Cannot rewind a generator that was already run.');
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
                $this->fill();
                return;
            }

            $this->result = $result;
            $this->finished = true;
        } catch (\Throwable $e) {
            $this->handleException($e);
            throw $e;
        } finally {
            unset($this->handler, $this->values);
        }
    }

    private function fill(): void
    {
        $this->key = $this->generator->key();
        $this->value = $this->generator->current();
        $this->finished = !$this->generator->valid() and $this->result = $this->generator->getReturn();
    }

    private function handleException(\Throwable $e): void
    {
        $this->key = null;
        $this->value = null;
        $this->finished = true;
        foreach ($this->catchers as $catch) {
            $catch($e);
        }
    }
}
