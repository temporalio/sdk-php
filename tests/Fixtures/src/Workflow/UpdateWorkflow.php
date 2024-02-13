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
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class UpdateWorkflow
{
    private array $greetings = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Update.greet")]
    public function greet()
    {
        yield Workflow::await(fn() => $this->exit);
        return $this->greetings;
    }

    #[Workflow\UpdateMethod]
    public function addName(string $name): mixed
    {
        $this->greetings[] = \sprintf('Hello, %s!', $name);
        return $this->greetings;
    }

    #[Workflow\UpdateMethod]
    public function throwException(string $name): mixed
    {
        throw new \Exception("Test exception with $name");
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
