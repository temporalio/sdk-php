<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

/**
 * State an operation can be in.
 */
enum OperationState: string
{
    /**
     * Indicates an operation is started and not yet completed.
     *
     * Value matches the lowercase string defined in the Nexus spec:
     * https://github.com/nexus-rpc/api/blob/main/SPEC.md
     */
    case Running = 'running';

    /**
     * Indicates an operation completed successfully.
     */
    case Succeeded = 'succeeded';

    /**
     * Indicates an operation completed as failed.
     */
    case Failed = 'failed';

    /**
     * Indicates an operation completed as canceled.
     */
    case Canceled = 'canceled';
}
