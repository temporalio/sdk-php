<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Marshaller\Meta\Marshal;

#[Immutable]
final class UpdateContext
{
    /**
     * @param non-empty-string $updateId
     */
    public function __construct(
        #[Marshal(name: 'UpdateId')]
        private string $updateId,
    ) {}

    public static function fromInput(UpdateInput $input): self
    {
        return new self($input->updateId);
    }

    /**
     * @return non-empty-string
     */
    public function getUpdateId(): string
    {
        return $this->updateId;
    }
}
