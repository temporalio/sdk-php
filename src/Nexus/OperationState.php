<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

enum OperationState: string
{
    // Wire values — must match the Nexus spec verbatim.
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
