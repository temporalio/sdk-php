<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;

/**
 * Construct via {@see OperationStartResult::async()}.
 *
 * @extends OperationStartResult<never>
 */
final readonly class AsyncOperationStartResult extends OperationStartResult
{
    /**
     * @internal
     */
    public function __construct(public OperationInfo $info)
    {
        if ($info->state !== OperationState::Running) {
            throw new InvalidArgumentException(\sprintf(
                'Async operation start must report a running operation, got state "%s".',
                $info->state->value,
            ));
        }

        parent::__construct();
    }
}
