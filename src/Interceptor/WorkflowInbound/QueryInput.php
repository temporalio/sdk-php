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
        public HeaderInterface $header,
    ) {
    }
}
