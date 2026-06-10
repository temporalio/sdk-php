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
    // Values match the lowercase strings defined in the Nexus spec:
    // https://github.com/nexus-rpc/api/blob/main/SPEC.md
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
