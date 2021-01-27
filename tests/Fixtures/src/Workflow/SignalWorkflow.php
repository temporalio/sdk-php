<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class SignalWorkflow
{
    private array $greetings = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Signal.greet")]
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
    public function addName(
        string $name
    ): void {
        $this->greetings[] = sprintf('Hello, %s!', $name);
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
