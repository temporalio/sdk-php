<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Api\Nexus\V1\HandlerError;

/**
 * Wraps a Nexus HandlerError proto for transport back to RR.
 *
 * @internal
 */
final class NexusHandlerErrorException extends \RuntimeException
{
    public function __construct(
        public readonly HandlerError $handlerError,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($handlerError->getFailure()?->getMessage() ?? 'handler error', 0, $previous);
    }
}
