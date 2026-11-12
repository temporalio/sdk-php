<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage;

use Temporal\Api\Common\V1\Payload;

/**
 * @experimental This API is experimental and may change in the future.
 */
interface StorageDriverLimiterInterface
{
    /**
     * @template TResult
     * @param \Closure(): TResult $operation
     * @return TResult
     */
    public function permit(Payload|StorageDriverClaim $item, \Closure $operation): mixed;
}
