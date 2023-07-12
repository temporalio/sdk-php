<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use InvalidArgumentException;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class SignalExceptionsWorkflow
{
    private array $greetings = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "SignalExceptions.greet")]
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

    #[SignalMethod]
    public function failWithName(string $name): void
    {
        $this->greetings[] = $name;
        throw new \RuntimeException("Signal exception $name");
    }

    #[SignalMethod]
    public function failInvalidArgument($name = 'foo'): void
    {
        $this->greetings[] = "invalidArgument $name";
        throw new InvalidArgumentException("Invalid argument $name");
    }

    #[SignalMethod]
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

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
