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

#[Workflow\WorkflowInterface]
class VersionedWorkflow
{
    #[WorkflowMethod(name: 'VersionedWorkflow')]
    public function handler(): iterable
    {
        return yield Workflow::getVersion('change-1', Workflow::DEFAULT_VERSION, 5);
    }
}
