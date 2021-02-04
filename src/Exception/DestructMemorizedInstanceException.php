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
 * Internal exception used to guide coroutines on their path to offload from
 * memory. Used by the DestroyWorkflow command.
 *
 * @internal
 */
class DestructMemorizedInstanceException extends TemporalException
{
}
