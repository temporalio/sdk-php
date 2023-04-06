<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\Header;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class EmptyHeaderWorkflow extends HeaderWorkflow
{
    public const WORKFLOW_NAME = 'HeaderEmptyHeaderWorkflow';

    #[WorkflowMethod(name: self::WORKFLOW_NAME)]
    public function handler(
        \stdClass|array|bool $subWorkflowHeader = false,
        \stdClass|array|null $activityHeader = null,
    ): iterable {
        return parent::handler($subWorkflowHeader, $activityHeader);
    }
}
