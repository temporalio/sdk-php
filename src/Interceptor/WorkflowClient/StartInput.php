<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;

/**
 * @psalm-immutable
 */
class StartInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $workflowId,
        public readonly string $workflowType,
        public readonly HeaderInterface $header,
        public readonly ValuesInterface $arguments,
        public readonly WorkflowOptions $options,
    ) {
    }

    public function with(
        HeaderInterface $header = null,
        ValuesInterface $arguments = null,
        WorkflowOptions $options = null,
    ): self {
        return new self(
            $this->workflowId,
            $this->workflowType,
            $header ?? $this->header,
            $arguments ?? $this->arguments,
            $options ?? $this->options,
        );
    }
}
