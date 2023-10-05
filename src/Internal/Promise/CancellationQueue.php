<?php

declare(strict_types=1);

namespace Temporal\Internal\Promise;

/**
 * @internal
 * @psalm-internal Temporal
 */
class CancellationQueue
{
    private bool $started = false;
    private array $queue = [];

    public function __invoke()
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->drain();
    }

    public function enqueue(mixed $cancellable): void
    {
        if (!\is_object($cancellable)
            || !\method_exists($cancellable, 'then')
            || !\method_exists($cancellable, 'cancel')
        ) {
            return;
        }

        $length = \array_push($this->queue, $cancellable);

        if ($this->started && 1 === $length) {
            $this->drain();
        }
    }

    private function drain(): void
    {
        for ($i = \key($this->queue); isset($this->queue[$i]); $i++) {
            $cancellable = $this->queue[$i];

            $exception = null;

            try {
                $cancellable->cancel();
            } catch (\Throwable $exception) {
            }

            unset($this->queue[$i]);

            if ($exception) {
                throw $exception;
            }
        }

        $this->queue = [];
    }
}
