<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\DataConverter\ValuesInterface;

final class StartUpdateOutput
{
    public function __construct(
        private readonly UpdateRef $reference,
        private readonly bool $hasResult,
        private readonly ?ValuesInterface $result,
    ) {
    }

    public function getReference(): UpdateRef
    {
        return $this->reference;
    }

    public function hasResult(): bool
    {
        return $this->hasResult;
    }

    public function getResult(): ?ValuesInterface
    {
        return $this->result;
    }
}
