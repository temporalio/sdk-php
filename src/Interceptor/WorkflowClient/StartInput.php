<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use JetBrains\PhpStorm\Immutable;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\HeaderInterface;
use Temporal\DataConverter\ValuesInterface;

/**
 * @psalm-immutable
 */
#[Immutable]
class StartInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        // todo: delete redundant property? because it exists in WorkflowOptions
        public string $workflowId,
        #[Immutable]
        public string $workflowType,
        #[Immutable]
        public HeaderInterface $header,
        #[Immutable]
        public ValuesInterface $arguments,
        #[Immutable]
        public WorkflowOptions $options,
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
