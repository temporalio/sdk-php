<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\ActivityClient;

use Temporal\Client\Activity\StartActivityOptions;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;

/**
 * @psalm-immutable
 */
class StartInput
{
    /**
     * @no-named-arguments
     * @internal
     */
    public function __construct(
        public readonly string $activityType,
        public readonly HeaderInterface $header,
        public readonly ValuesInterface $arguments,
        public readonly StartActivityOptions $options,
    ) {}

    public function with(
        ?HeaderInterface $header = null,
        ?ValuesInterface $arguments = null,
        ?StartActivityOptions $options = null,
    ): self {
        return new self(
            $this->activityType,
            $header ?? $this->header,
            $arguments ?? $this->arguments,
            $options ?? $this->options,
        );
    }
}
