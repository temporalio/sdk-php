<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\ActivityClient;

/**
 * @psalm-immutable
 */
class DescribeInput
{
    /**
     * @no-named-arguments
     * @internal
     */
    public function __construct(
        public readonly string $activityId,
        public readonly ?string $runId,
    ) {}
}
