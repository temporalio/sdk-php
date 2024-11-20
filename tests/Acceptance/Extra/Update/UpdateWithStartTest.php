<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Update\UpdateWithStart;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Internal\Support\DateInterval;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class UpdateWithStartTest extends TestCase
{
    #[Test]
    public function fetchResolvedResultAfterWorkflowCompleted(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );

        /** @see TestWorkflow::add */
        $handle = $client->updateWithStart($stub, 'await', ['key']);

        // Complete workflow
        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame(['key' => null], (array)$result);
        $this->assertFalse($handle->hasResult());
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $awaits = [];
    private bool $updateStarted = false;
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Update_UpdateWithStart")]
    public function handle()
    {
        $this->updateStarted or throw new \RuntimeException('Not started with update');
        yield Workflow::await(fn() => $this->exit);
        return $this->awaits;
    }

    /**
     * @param non-empty-string $name
     * @return mixed
     */
    #[Workflow\UpdateMethod(name: 'await')]
    public function add(string $name): mixed
    {
        $this->updateStarted = true;
        $this->awaits[$name] ??= null;
        yield Workflow::await(fn() => $this->awaits[$name] !== null);
        return $this->awaits[$name];
    }

    #[Workflow\UpdateValidatorMethod(forUpdate: 'await')]
    public function validateAdd(string $name): void
    {
        empty($name) and throw new \InvalidArgumentException('Name must not be empty');
    }

    /**
     * @param non-empty-string $name
     * @return PromiseInterface<bool>
     */
    #[Workflow\UpdateMethod(name: 'awaitWithTimeout')]
    public function addWithTimeout(string $name, string|int $timeout, mixed $value): mixed
    {
        $this->updateStarted = true;
        $this->awaits[$name] ??= null;
        if ($this->awaits[$name] !== null) {
            return $this->awaits[$name];
        }

        $notTimeout = yield Workflow::awaitWithTimeout(
            $timeout,
            fn() => $this->awaits[$name] !== null,
        );

        if (!$notTimeout) {
            return $this->awaits[$name] = $value;
        }

        return $this->awaits[$name];
    }

    #[Workflow\UpdateValidatorMethod(forUpdate: 'awaitWithTimeout')]
    public function validateAddWithTimeout(string $name, string|int $timeout, mixed $value): void
    {
        $value === null and throw new \InvalidArgumentException('Value must not be null');
        empty($name) and throw new \InvalidArgumentException('Name must not be empty');
        DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS)->isEmpty() and throw new \InvalidArgumentException(
            'Timeout must not be empty'
        );
    }

    /**
     * @param non-empty-string $name
     * @return mixed
     */
    #[Workflow\UpdateMethod(name: 'resolveValue')]
    public function resolve(string $name, mixed $value): mixed
    {
        $this->updateStarted = true;
        return $this->awaits[$name] = $value;
    }

    #[Workflow\UpdateValidatorMethod(forUpdate: 'resolveValue')]
    public function validateResolve(string $name, mixed $value): void
    {
        $value === null and throw new \InvalidArgumentException('Value must not be null');
        \array_key_exists($name, $this->awaits) or throw new \InvalidArgumentException('Name not found');
        $this->awaits[$name] === null or throw new \InvalidArgumentException('Name already resolved');
    }

    /**
     * @param non-empty-string $name
     * @return mixed
     */
    #[Workflow\QueryMethod(name: 'getValue')]
    public function get(string $name): mixed
    {
        return $this->awaits[$name] ?? null;
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
