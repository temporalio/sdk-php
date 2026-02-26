<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class DetachedScopeWorkflow
{
    #[WorkflowMethod]
    public function handler()
    {
        yield Workflow::asyncDetached(
            static function (): void {
                // Don't add `yield` here. It's important for the tests.
                Workflow::await(Workflow::timer(5000));
            },
        );

        return 'ok';
    }
}
