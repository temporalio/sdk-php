<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Tests\Activity\SampleActivityInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ActivityReturnTypeWorkflow
{
    #[WorkflowMethod]
    public function handler()
    {
        // typed stub
        $act = Workflow::newActivityStub(
            SampleActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)
        );

        $value = yield $act->multiply(10);
        yield $act->store($value);

        return $value;
    }
}
