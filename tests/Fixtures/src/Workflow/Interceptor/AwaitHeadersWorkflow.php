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
class AwaitHeadersWorkflow
{
    #[WorkflowMethod(name: 'InterceptorAwaitHeaderWorkflow')]
    public function handler(): array
    {
        Workflow::timer(1);
        Workflow::awaitWithTimeout(1, static fn() => false);

        return [
            \iterator_to_array(Workflow::getCurrentContext()->getHeader()),
        ];
    }
}
