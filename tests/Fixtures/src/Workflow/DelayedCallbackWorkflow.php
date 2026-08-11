<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class DelayedCallbackWorkflow
{
    /** @var array<string, int> tag => virtual epoch seconds when the callback fired */
    private array $fired = [];

    /**
     * @param list<array{0: int, 1: string}> $schedule pairs of [delaySeconds, tag]
     */
    #[WorkflowMethod(name: 'DelayedCallbackWorkflow')]
    public function handler(array $schedule): iterable
    {
        $scopes = [];
        foreach ($schedule as [$delaySeconds, $tag]) {
            $scopes[] = Workflow::async(function () use ($delaySeconds, $tag): \Generator {
                yield Workflow::timer($delaySeconds);
                $this->fired[$tag] = Workflow::now()->getTimestamp();
            });
        }

        yield Promise::all($scopes);

        return $this->fired;
    }
}
