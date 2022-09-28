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
class UpsertSearchAttributesWorkflow
{
    #[WorkflowMethod]
    public function handler()
    {
        Workflow::upsertSearchAttributes(
            [
                'attr1' => 'attr1-value',
                'attr2' => true,
            ]
        );

        return 'done';
    }
}
