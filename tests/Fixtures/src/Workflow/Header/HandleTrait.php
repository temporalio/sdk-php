<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\Header;

use Generator;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;

trait HandleTrait
{
    /**
     * @param array|null $activityHeader Header for activity that will be set by {@see HeaderChanger} interceptor:
     *        - null: run activity with {@see null} header value
     *        - array: will be passed into activity as is without merging with workflow header
     *
     * @return Generator<mixed, mixed, mixed, array{array, array}> Returns array of headers:
     *         - [0] - header from current workflow
     *         - [1] - header from activity
     */
    protected function runActivity(
        array|null $activityHeader = null,
    ): iterable {
        // Run activity
        $activityResult = yield Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(2),
                ),
        )->header();

        return [
            \iterator_to_array(Workflow::getCurrentContext()->getHeader()),
            $activityResult,
        ];
    }
}
