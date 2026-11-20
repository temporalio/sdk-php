<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class StorageDriverStoreContext
{
    public function __construct(
        public readonly ?StorageDriverTargetInfo $target,
        public readonly string $namespace,
        public readonly StorageDriverLimiterInterface $limiter,
    ) {}
}
