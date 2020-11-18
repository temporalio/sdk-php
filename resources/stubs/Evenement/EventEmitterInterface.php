<?php

declare(strict_types=1);

namespace Evenement;

/**
 * @template T of string
 */
interface EventEmitterInterface
{
    /**
     * @psalm-param T $event
     */
    public function on($event, callable $listener);

    /**
     * @psalm-param T $event
     */
    public function once($event, callable $listener);

    /**
     * @psalm-param T $event
     */
    public function removeListener($event, callable $listener);

    /**
     * @psalm-param T|null $event
     */
    public function removeAllListeners($event = null);

    /**
     * @psalm-param T $event
     */
    public function listeners($event = null);

    /**
     * @psalm-param T $event
     */
    public function emit($event, array $arguments = []);
}
