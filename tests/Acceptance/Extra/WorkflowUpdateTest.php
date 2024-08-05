<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\WorkflowUpdate;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Internal\Support\DateInterval;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class WorkflowUpdateTest extends TestCase
{
    #[Test]
    public function fetchResolvedResultAfterWorkflowCompleted(
        #[Stub('Extra_WorkflowUpdate')]WorkflowStubInterface $stub,
    ): void
    {
        /** @see TestWorkflow::add */
        $handle = $stub->startUpdate('await', 'key');

        /** @see TestWorkflow::resolve */
        $resolver = $stub->startUpdate('resolveValue', "key", "resolved");

        // Complete workflow
        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame(['key' => 'resolved'], (array)$result, 'Workflow result contains resolved value');
        $this->assertFalse($handle->hasResult());
        $this->assertFalse($resolver->hasResult(), 'Resolver should not have result because of wait policy');
        // Fetch result
        $this->assertSame('resolved', $handle->getResult());
        $this->assertTrue($handle->hasResult());
    }

    #[Test]
    public function fetchResultWithTimeout(
        #[Stub('Extra_WorkflowUpdate')]WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::add */
        $handle = $stub->startUpdate('await', 'key');

        try {
            $start = \microtime(true);
            $handle->getResult(0.2);
            $this->fail('Should throw exception');
        } catch (TimeoutException) {
            $elapsed = \microtime(true) - $start;
            $this->assertFalse($handle->hasResult());
            $this->assertLessThan(1.0, $elapsed);
            $this->assertGreaterThan(0.2, $elapsed);
        }

        // Complete workflow
        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();
        $this->assertSame(['key' => null], (array)$result, 'Workflow result contains resolved value');
    }

    #[Test]
    public function handleUnknownUpdate(
        #[Stub('Extra_WorkflowUpdate')]WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->startUpdate('unknownUpdateMethod', '42');
            $this->fail('Should throw exception');
        } catch (WorkflowUpdateException $e) {
            $this->assertStringContainsString(
                'unknown update method unknownUpdateMethod',
                $e->getPrevious()->getMessage(),
            );
        }
    }

    #[Test]
    public function singleAwaitsWithoutTimeout(
        #[Stub('Extra_WorkflowUpdate')]WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::add */
        $handle = $stub->startUpdate('await', 'key');
        $this->assertFalse($handle->hasResult());

        /** @see TestWorkflow::get */
        $this->assertNull($stub->query('getValue', "key")->getValue(0));

        /** @see TestWorkflow::resolve */
        $handle = $stub->update('resolveValue', "key", "resolved");
        $this->assertSame("resolved", $handle->getValue(0));

        /** @see TestWorkflow::get */
        $this->assertSame("resolved", $stub->query('getValue', "key")->getValue(0));

        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame(['key' => 'resolved'], (array)$result);
    }

    #[Test]
    public function sultipleAwaitsWithoutTimeout(
        #[Stub('Extra_WorkflowUpdate')]WorkflowStubInterface $stub,
    ): void {
        for ($i = 1; $i <= 5; $i++) {
            /** @see TestWorkflow::add */
            $handle = $stub->startUpdate('await', "key$i", 5, "fallback$i");
            $this->assertFalse($handle->hasResult());

            /** @see TestWorkflow::get */
            $this->assertNull($stub->query('getValue', "key$i")->getValue(0));
        }

        for ($i = 1; $i <= 5; $i++) {
            /** @see TestWorkflow::resolve */
            $handle = $stub->update('resolveValue', "key$i", "resolved$i");
            $this->assertSame("resolved$i", $handle->getValue(0));

            /** @see TestWorkflow::get */
            $this->assertSame("resolved$i", $stub->query('getValue', "key$i")->getValue(0));
        }

        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame([
            'key1' => 'resolved1',
            'key2' => 'resolved2',
            'key3' => 'resolved3',
            'key4' => 'resolved4',
            'key5' => 'resolved5',
        ], (array)$result);
    }

    #[Test]
    public function sultipleAwaitsWithTimeout(
        #[Stub('Extra_WorkflowUpdate')]WorkflowStubInterface $stub,
    ): void {
        for ($i = 1; $i <= 5; $i++) {
            /** @see TestWorkflow::addWithTimeout */
            $handle = $stub->startUpdate('awaitWithTimeout', "key$i", 5, "fallback$i");
            $this->assertFalse($handle->hasResult());
        }

        for ($i = 1; $i <= 5; $i++) {
            /** @see TestWorkflow::resolve */
            $stub->startUpdate('resolveValue', "key$i", "resolved$i");
        }

        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame([
            'key1' => 'resolved1',
            'key2' => 'resolved2',
            'key3' => 'resolved3',
            'key4' => 'resolved4',
            'key5' => 'resolved5',
        ], (array)$result);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    private array $awaits = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_WorkflowUpdate")]
    public function handle()
    {
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
