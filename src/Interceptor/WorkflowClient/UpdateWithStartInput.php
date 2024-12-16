<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

/**
 * @psalm-immutable
 */
class UpdateWithStartInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly StartInput $workflowStartInput,
        public readonly UpdateInput $updateInput,
    ) {}

    public function with(
        ?StartInput $workflowStartInput = null,
        ?UpdateInput $updateInput = null,
    ): self {
        return new self(
            $workflowStartInput ?? $this->workflowStartInput,
            $updateInput ?? $this->updateInput,
        );
    }
}
