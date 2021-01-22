<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Temporal\Common\MethodRetry;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class ChildWorkflowMethods extends ParentWorkflowMethods
{

    protected function handler(): void
    {
    }

    #[SignalMethod]
    protected function test(): void
    {

    }
}
