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
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** @WorkflowInterface */
#[WorkflowInterface]
class WorkflowWithRetry
{
    /**
     * @WorkflowMethod
     * @MethodRetry(initialInterval="42µs")
     */
    #[WorkflowMethod, MethodRetry('42µs')]
    public function handler(): void
    {
    }
}
