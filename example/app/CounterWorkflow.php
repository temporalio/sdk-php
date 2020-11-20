<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Client\Workflow;

#[Workflow\Meta\WorkflowInterface]
class CounterWorkflow
{
    /**
     * @var positive-int
     */
    private const MAX_VALUE = 100;

    /**
     * @var positive-int
     */
    private const DELAY_SECONDS = 10;

    /**
     * @var positive-int
     */
    private int $value = 0;

    #[Workflow\Meta\WorkflowMethod(name: 'CounterWorkflow')]
    public function handle(): iterable
    {
        while ($this->value++ < self::MAX_VALUE) {
            yield Workflow::timer(self::DELAY_SECONDS);
        }
    }

    #[Workflow\Meta\QueryMethod]
    public function getValue(): int
    {
        return $this->value;
    }

    #[Workflow\Meta\QueryMethod]
    public function getServerTime(): \DateTimeInterface
    {
        return Workflow::now();
    }

    #[Workflow\Meta\SignalMethod]
    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    #[Workflow\Meta\SignalMethod]
    public function nextValue(): void
    {
        $this->value++;
    }
}
