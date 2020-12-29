<?php

namespace Temporal\Client\Internal\Queue;

final class SignalQueue
{
    private array $queue = [];
    private array $consumers = [];

    /**
     * @param string $signal
     * @param array $args
     */
    public function push(string $signal, array $args): void
    {
        if (isset($this->consumers[$signal])) {
            ($this->consumers[$signal])($args);
            return;
        }

        if (!isset($this->queue[$signal])) {
            $this->queue[$signal] = [];
            $this->queue[$signal][] = $args;
        } else {
            $this->queue[$signal][] = $args;
            $this->flush($signal);
        }
    }

    /**
     * @param string $signal
     * @param callable $consumer
     */
    public function attach(string $signal, callable $consumer): void
    {
        $this->consumers[$signal] = $consumer; // overwrite
        $this->flush($signal);
    }

    /**
     * @param string $signal
     */
    private function flush(string $signal): void
    {
        if (!isset($this->queue[$signal]) || !isset($this->consumers[$signal])) {
            return;
        }

        while ($this->queue[$signal] !== []) {
            $args = array_shift($this->queue[$signal]);
            ($this->consumers[$signal])($args);
        }
    }
}
