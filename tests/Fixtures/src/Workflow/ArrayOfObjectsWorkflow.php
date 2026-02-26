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
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\Type;
use Temporal\Tests\DTO\Message;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class ArrayOfObjectsWorkflow
{
    #[WorkflowMethod(name: 'ArrayOfObjectsWorkflow')]
    public function handler(
        string $input
    ): iterable {
        $activity = Workflow::newUntypedActivityStub(
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2)
                )
        );

        return yield $activity->execute('SimpleActivity.arrayOfObjects', [$input], Type::arrayOf(Message::class));
    }
}
