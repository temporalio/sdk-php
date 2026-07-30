<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception;

/**
 * Thrown into a workflow Fiber when it suspends with a value that is not part of the
 * workflow suspension protocol (a Promise, Mutex, Deferred or outbound Request).
 *
 * The usual cause is calling a non-workflow asynchronous API (e.g. a service client that
 * uses {@see \Fiber::suspend()} for its own scheduler) from inside a workflow body.
 */
class InvalidSuspendException extends TemporalException {}
