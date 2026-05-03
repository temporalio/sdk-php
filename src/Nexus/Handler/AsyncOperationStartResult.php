<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\OperationInfo;

/**
 * Carries the {@see OperationInfo} (token + state). Mirrors the spec's
 * `201 Created` body for `StartOperation`. Construct via
 * {@see OperationStartResult::async()}.
 *
 * @extends OperationStartResult<never>
 */
final readonly class AsyncOperationStartResult extends OperationStartResult
{
    /** @internal */
    public function __construct(public OperationInfo $info)
    {
        parent::__construct();
    }
}
