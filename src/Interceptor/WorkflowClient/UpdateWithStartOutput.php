<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\Client\Update\UpdateHandle;
use Temporal\Workflow\WorkflowExecution;

final class UpdateWithStartOutput
{
    public function __construct(
        private readonly WorkflowExecution $execution,
        private readonly UpdateHandle|\Throwable $handle,
    ) {}

    public function getExecution(): WorkflowExecution
    {
        return $this->execution;
    }

    public function getHandle(): UpdateHandle
    {
        if ($this->handle instanceof \Throwable) {
            throw $this->handle;
        }

        return $this->handle;
    }
}
