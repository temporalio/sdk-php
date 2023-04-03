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
class SignalHeadersWorkflow
{
    private ?array $headers = null;
    private bool $signalled = false;

    #[WorkflowMethod(name: 'InterceptorSignalHeadersWorkflow')]
    public function handler(): mixed
    {
        yield Workflow::await(fn() => $this->signalled);
        return $this->headers;
    }

    #[Workflow\SignalMethod]
    public function signal(): void
    {
        $this->signalled = true;
        $this->headers = \iterator_to_array(Workflow::getCurrentContext()->getHeader()->getIterator());
    }
}
