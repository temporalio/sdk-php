<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowInbound;

use Temporal\DataConverter\HeaderInterface;
use Temporal\DataConverter\ValuesInterface;

class QueryInput
{
    public function __construct(
        public string $queryName,
        public ValuesInterface $arguments,
        // todo: remove headers
        public HeaderInterface $header,
    ) {
    }

    public function with(
        ValuesInterface $arguments = null,
        HeaderInterface $header = null,
    ): self {
        return new self(
            $this->queryName,
            $arguments ?? $this->arguments,
            $header ?? $this->header
        );
    }
}
