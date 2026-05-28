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
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Tests\DTO\Message;
use Temporal\Tests\DTO\User;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class SimpleDTOWorkflow
{
    #[WorkflowMethod(name: 'SimpleDTOWorkflow')]
    #[ReturnType(Message::class)]
    public function handler(
        User $user
    ) {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
        );

        $value = yield $simple->greet($user);

        if (!$value instanceof Message) {
            return "FAIL";
        }

        return $value;
    }
}
