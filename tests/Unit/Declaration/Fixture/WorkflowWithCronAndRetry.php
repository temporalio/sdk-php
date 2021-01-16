<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixture;

use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Workflow\WorkflowMethod;

class WorkflowWithCronAndRetry
{
    /**
     * @WorkflowMethod
     * @CronSchedule(interval="@monthly")
     * @MethodRetry(initialInterval="42µs")
     */
    #[WorkflowMethod, CronSchedule('@monthly'), MethodRetry('42µs')]
    public function handler(): void
    {
    }
}
