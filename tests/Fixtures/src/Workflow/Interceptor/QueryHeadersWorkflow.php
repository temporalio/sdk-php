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
class QueryHeadersWorkflow
{
    private bool $signalled = false;

    #[WorkflowMethod(name: 'InterceptorQueryHeadersWorkflow')]
    public function handler(): mixed
    {
        yield Workflow::await(fn() => $this->signalled);
    }

    #[Workflow\SignalMethod]
    public function signal(): void
    {
        $this->signalled = true;
    }

    #[Workflow\QueryMethod]
    public function getHeaders(): array
    {
        return \iterator_to_array(Workflow::getHeader()->getIterator());
    }

    #[Workflow\QueryMethod]
    public function getContext(): array
    {
        return [
            'RunId' => Workflow::getRunId(),
            'ContextId' => Workflow::getContextId(),
            'Info' => Workflow::getInfo(),
        ];
    }
}
