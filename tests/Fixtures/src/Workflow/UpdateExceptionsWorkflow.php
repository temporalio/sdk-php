<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Carbon\CarbonInterval;
use InvalidArgumentException;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\UpdateMethod;

#[WorkflowInterface]
class UpdateExceptionsWorkflow
{
    private array $greetings = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "UpdateExceptionsWorkflow.greet")]
    public function greet()
    {
        $received = [];
        while (true) {
            yield Workflow::await(fn() => $this->greetings !== [] || $this->exit);
            if ($this->greetings === [] && $this->exit) {
                return $received;
            }

            $message = array_shift($this->greetings);
            $received[] = $message;
        }
    }

    #[UpdateMethod]
    public function failWithName(string $name): void
    {
        $this->greetings[] = $name;
        throw new \RuntimeException("Signal exception $name");
    }

    #[UpdateMethod]
    public function failInvalidArgument($name = 'foo'): void
    {
        $this->greetings[] = "invalidArgument $name";
        throw new InvalidArgumentException("Invalid argument $name");
    }

    #[UpdateMethod]
    public function failActivity($name = 'foo')
    {
        yield Workflow::newUntypedActivityStub(
            ActivityOptions::new()
                ->withScheduleToStartTimeout(1)
                ->withRetryOptions(
                    RetryOptions::new()->withMaximumAttempts(1)
                )
                ->withStartToCloseTimeout(1),
        )->execute('nonExistingActivityName', [$name]);
    }

    #[UpdateMethod]
    public function error()
    {
        yield Workflow::timer(CarbonInterval::millisecond(10));
        10 / 0;
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
