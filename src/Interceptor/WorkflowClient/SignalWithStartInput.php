<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use JetBrains\PhpStorm\Immutable;
use Temporal\DataConverter\ValuesInterface;

/**
 * @psalm-immutable
 */
#[Immutable]
class SignalWithStartInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public StartInput $workflowStartInput,
        #[Immutable]
        public string $signalName,
        #[Immutable]
        public ValuesInterface $signalArguments,
    ) {
    }

    public function with(
        StartInput $workflowStartInput = null,
        string $signalName = null,
        ValuesInterface $signalArguments = null,
    ): self {
        return new self(
            $workflowStartInput ?? $this->workflowStartInput,
            $signalName ?? $this->signalName,
            $signalArguments ?? $this->signalArguments,
        );
    }
}
