<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowInbound;

use Temporal\Interceptor\HeaderInterface;
use Temporal\Workflow\WorkflowInfo;

/**
 * @psalm-immutable
 */
class InitInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly WorkflowInfo $info,
        public readonly HeaderInterface $header,
    ) {}

    public function with(
        ?WorkflowInfo $info = null,
        ?HeaderInterface $header = null,
    ): self {
        return new self(
            $info ?? $this->info,
            $header ?? $this->header,
        );
    }
}
