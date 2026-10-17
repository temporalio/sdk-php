<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

/**
 * Construct via {@see OperationStartResult::sync()}, not `new`.
 *
 * @template R
 * @extends OperationStartResult<R>
 */
final readonly class SyncOperationStartResult extends OperationStartResult
{
    /**
     * @internal
     * @param R|null $value
     */
    public function __construct(
        public mixed $value,
    ) {
        parent::__construct();
    }
}
