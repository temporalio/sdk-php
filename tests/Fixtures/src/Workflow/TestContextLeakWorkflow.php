<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use React\Promise\PromiseInterface;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;

use function React\Promise\resolve;

#[WorkflowInterface]
class TestContextLeakWorkflow
{
    private string $workflowId = '';
    private string $runId = '';
    private CustomTimer $timer;

    #[WorkflowMethod(name: 'TestContextLeakWorkflow')]
    public function handler(): iterable
    {
        $this->workflowId = Workflow::getInfo()->execution->getID();
        $this->runId = Workflow::getInfo()->execution->getRunID();

        $this->checkContext();

        $this->timer = new CustomTimer(Workflow::getInfo()->execution);

        $timer = yield $this->timer->sleepUntil(new DateTimeImmutable('@' . (Workflow::now()->getTimestamp() + 5)));

        $this->checkContext();

        return $timer;
    }

    #[SignalMethod]
    public function cancel(): void
    {
        $this->checkContext();
        $this->timer->cancel();
        $this->checkContext();
    }

    #[QueryMethod()]
    public function wakeup(): DateTimeInterface
    {
        $this->checkContext();
        return $this->timer->getWakeUpTime();
    }

    private function checkContext(): void
    {
        $this->workflowId === Workflow::getInfo()->execution->getID()
        && $this->runId === Workflow::getInfo()->execution->getRunID()
        or throw new ApplicationFailure('Context leak detected!', 'CONTEXT_LEAK', true);
    }
}


class CustomTimer
{
    private DateTimeInterface $wakeUpTime;
    private bool $isWakeUpTimeUpdated = false;
    private bool $isCancelled = false;

    public function __construct(
        private WorkflowExecution $execution,
    ) {
    }

    /**
     * Returns a promise that resolves to
     *  - `true` if the timer sleeps until `$wakeUpTime`.
     *  - `false` if the timer was interrupted by a cancellation, or if `$wakeUpTime` is in the past.
     * @return Generator<int, PromiseInterface<bool>, bool, PromiseInterface<bool>>
     */
    public function sleepUntil(DateTimeInterface $wakeUpTime): Generator
    {
        $this->wakeUpTime = $wakeUpTime;

        while (true) {
            $this->checkContext();
            if ($this->isCancelled) {
                return resolve(false);
            }

            $this->isWakeUpTimeUpdated = false;
            $sleepInterval = $this->wakeUpTime->getTimestamp() - Workflow::now()->getTimestamp();

            if ($sleepInterval <= 0) {
                return resolve(false);
            }

            if (!yield Workflow::awaitWithTimeout(
                $sleepInterval,
                function () {
                    $this->checkContext();
                    return $this->isWakeUpTimeUpdated || $this->isCancelled;
                },
            )) {
                $this->checkContext();
                return resolve(true);
            }
        }
    }

    public function updateWakeUpTime(DateTimeInterface $wakeUpTime): void
    {
        $this->wakeUpTime = $wakeUpTime;
        $this->isWakeUpTimeUpdated = true;
    }

    public function getWakeUpTime(): DateTimeInterface
    {
        return $this->wakeUpTime;
    }

    public function cancel(): void
    {
        $this->isCancelled = true;
    }

    private function checkContext(): void
    {
        $this->execution->getID() === Workflow::getInfo()->execution->getID()
        && $this->execution->getRunID() === Workflow::getInfo()->execution->getRunID()
        or throw new ApplicationFailure('Context leak detected in attached context!', 'CONTEXT_LEAK', true);
    }
}
