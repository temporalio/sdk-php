<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Ramsey\Uuid\UuidInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class SimpleUuidWorkflow
{
    #[WorkflowMethod(name: 'SimpleUuidWorkflow')]
    #[Workflow\ReturnType(UuidInterface::class)]
    public function handler(UuidInterface $uuid): UuidInterface
    {
        return $uuid;
    }
}
