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
 * Thrown when workflow execution is suspended outside of the workflow suspension protocol.
 *
 * The usual causes are a non-workflow asynchronous API that runs its own scheduler on
 * {@see \Fiber::suspend()}, a suspending Temporal call made from a promise callback or a
 * query handler, and a workflow handler that returns a Generator or a Promise.
 */
class InvalidSuspendException extends TemporalException {}
