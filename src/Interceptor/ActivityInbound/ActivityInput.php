<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\ActivityInbound;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;

/**
 * @psalm-immutable
 */
class ActivityInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly ValuesInterface $arguments,
        public readonly HeaderInterface $header,
    ) {
    }

    public function with(
        ValuesInterface $arguments = null,
        HeaderInterface $header = null,
    ): self {
        return new self(
            $arguments ?? $this->arguments,
            $header ?? $this->header
        );
    }
}
