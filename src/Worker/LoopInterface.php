<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Internal\Events\EventListenerInterface;

/**
 * The {@see LoopInterface} is responsible for providing an interface for
 * creating an event loop.
 *
 * Besides defining a few methods, this interface also implements
 * the {@see EventListenerInterface} which allows you to react to certain events.
 */
interface LoopInterface extends EventListenerInterface
{
    /**
     * The `tick` event will be emitted whenever all Temporal commands were
     * processed and the internal state is ready to send new data to the server.
     *
     * ```php
     * $loop->once('tick', function() {
     *     echo 'tick';
     * });
     * ```
     *
     * This event MAY be emitted any number of times, which may be zero times
     * if this event loop does not send any data at all.
     *
     * @var string
     */
    public const ON_TICK = 'tick';

    /**
     * @var string
     */
    public const ON_SIGNAL = 'signal';

    /**
     * @var string
     */
    public const ON_QUERY = 'query';

    /**
     * @var string
     */
    public const ON_CALLBACK = 'callback';

    /**
     * Must be emitted at the end of each loop iteration after all other events.
     *
     * @var string
     */
    public const ON_FINALLY = 'finally';

    /**
     * @return void
     */
    public function tick(): void;

    /**
     * Run the event loop until there are no more tasks to perform.
     *
     * For many applications, this method is the only directly visible
     * invocation on the event loop.
     *
     * As a rule of thumb, it is usually recommended to attach everything to the
     * same loop instance and then run the loop once at the bottom end of the
     * application.
     *
     * ```php
     * $loop->run();
     * ```
     *
     * This method will keep the loop running until there are no more tasks
     * to perform. In other words: This method will block until the last
     * timer, stream and/or signal has been removed.
     *
     * Likewise, it is imperative to ensure the application actually invokes
     * this method once. Adding listeners to the loop and missing to actually
     * run it will result in the application exiting without actually waiting
     * for any of the attached listeners.
     *
     * This method MUST NOT be called while the loop is already running.
     *
     * @return int
     */
    public function run(): int;
}
