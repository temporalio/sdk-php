<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\ActivityInbound;

use JetBrains\PhpStorm\Immutable;
use Temporal\DataConverter\HeaderInterface;
use Temporal\DataConverter\ValuesInterface;

/**
 * @psalm-immutable
 */
#[Immutable]
class ActivityInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public ValuesInterface $arguments,
        #[Immutable]
        public HeaderInterface $header,
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
