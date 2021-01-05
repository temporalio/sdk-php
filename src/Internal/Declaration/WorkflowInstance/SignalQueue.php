<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

/**
 * @psalm-type Consumer = callable(array): mixed
 */
final class SignalQueue
{
    /**
     * @var array<string, array<array>>
     */
    private array $queue = [];

    /**
     * @var array<Consumer>
     */
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

        $this->queue[$signal][] = $args;
        $this->flush($signal);
    }

    /**
     * @param string $signal
     * @param Consumer $consumer
     * @return void
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
        if (!isset($this->queue[$signal], $this->consumers[$signal])) {
            return;
        }

        while ($this->queue[$signal] !== []) {
            $args = array_shift($this->queue[$signal]);

            ($this->consumers[$signal])($args);
        }
    }
}