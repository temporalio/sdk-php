<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class AwaitsUpdateWorkflow
{
    private array $awaits = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "AwaitsUpdate.greet")]
    public function greet()
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
