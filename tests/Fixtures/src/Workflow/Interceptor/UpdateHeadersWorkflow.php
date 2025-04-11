<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\Interceptor;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class UpdateHeadersWorkflow
{
    private ?array $headers = null;
    private bool $updated = false;

    #[WorkflowMethod(name: 'InterceptorUpdateHeadersWorkflow')]
    public function handler(): mixed
    {
        yield Workflow::await(fn() => $this->updated);

        $this->headers = \iterator_to_array(Workflow::getCurrentContext()->getHeader());

        return $this->headers;
    }

    #[Workflow\UpdateMethod]
    public function update(): void
    {
        $this->updated = true;
    }

    #[Workflow\QueryMethod]
    public function headers(): mixed
    {
        return $this->headers;
    }
}
