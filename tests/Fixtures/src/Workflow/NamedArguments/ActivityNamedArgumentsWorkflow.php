<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ActivityNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        string $string,
        bool $bool,
        string $secondString,
    ): \Generator|array {
        $activity = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2)
                )
        );

        $oneParamRes = yield $activity->namedArguments(
            input: $string,
        );

        $paramsInDifferentOrderRes = yield $activity->namedArguments(
            optionalNullableString: $secondString,
            optionalBool: $bool,
            input: $string,
        );

        $missingParamsRes = yield $activity->namedArguments(
            input: $string,
            optionalNullableString: $secondString,
        );

        $missingParamAndDifferentOrderRes = yield $activity->namedArguments(
            optionalNullableString: $secondString,
            input: $string,
        );

        return [
            'oneParamRes' => $oneParamRes,
            'paramsInDifferentOrderRes' => $paramsInDifferentOrderRes,
            'missingParamsRes' => $missingParamsRes,
            'missingParamAndDifferentOrderRes' => $missingParamAndDifferentOrderRes,
        ];
    }
}
