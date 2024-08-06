<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Feature\Testing;

use PHPUnit\Framework\Assert;
use Temporal\Internal\Queue\ArrayQueue;
use Temporal\Worker\Transport\Command\Client\Request;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class TestingQueue extends ArrayQueue
{
    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        while ($this->commands) {
            yield $this->pop();
        }
    }

    /**
     * @return TestingRequest|TestingSuccessResponse|TestingFailureResponse
     */
    public function pop(): CommandInterface
    {
        return \array_pop($this->commands);
    }

    /**
     * @return TestingRequest|TestingSuccessResponse|TestingFailureResponse
     */
    public function shift(): CommandInterface
    {
        return \array_shift($this->commands);
    }

    /**
     * {@inheritDoc}
     */
    public function push(CommandInterface $command): void
    {
        if ($command instanceof Request) {
            $command = new TestingRequest($command);
        }

        $this->commands[] = $command;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->commands = [];
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertCount(int $expected, string $message = ''): self
    {
        Assert::assertCount($expected, $this->commands, $message);

        return $this;
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertTypesCount(string $class, int $expected, string $message = ''): self
    {
        $filter = static fn (CommandInterface $cmd) => $cmd instanceof $class;

        Assert::assertCount($expected, \array_filter($this->commands, $filter), $message);

        return $this;
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertRequestsCount(int $expected, string $message = ''): self
    {
        $this->assertTypesCount(RequestInterface::class, $expected, $message);

        return $this;
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertResponsesCount(int $expected, string $message = ''): self
    {
        $this->assertTypesCount(ResponseInterface::class, $expected, $message);

        return $this;
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertErrorResponsesCount(int $expected, string $message = ''): self
    {
        $this->assertTypesCount(FailureResponseInterface::class, $expected, $message);

        return $this;
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertSuccessResponsesCount(int $expected, string $message = ''): self
    {
        $this->assertTypesCount(SuccessResponseInterface::class, $expected, $message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function assertEmpty(string $message = ''): self
    {
        $this->assertCount(0, $message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function assertNotEmpty(string $message = ''): self
    {
        Assert::assertTrue(\count($this->commands) > 0, $message);

        return $this;
    }

    /**
     * @param array $commands
     * @param string $message
     * @return $this
     */
    public function assertSame(array $commands, string $message = ''): self
    {
        Assert::assertSame($this->commands, $commands, $message);

        return $this;
    }

    /**
     * @param array $commands
     * @param string $message
     * @return $this
     */
    public function assertEquals(array $commands, string $message = ''): self
    {
        Assert::assertEquals($this->commands, $commands, $message);

        return $this;
    }

    /**
     * @return TestingRequest|TestingSuccessResponse|TestingFailureResponse
     */
    public function first(): CommandInterface
    {
        return $this->get(\array_key_first($this->commands));
    }

    /**
     * @return TestingRequest|TestingSuccessResponse|TestingFailureResponse
     */
    public function last(): CommandInterface
    {
        return $this->get(\array_key_first($this->commands));
    }

    /**
     * @param positive-int $index
     * @return TestingRequest|TestingSuccessResponse|TestingFailureResponse|null
     */
    public function find(int $index): ?CommandInterface
    {
        return $this->commands[$index] ?? null;
    }

    /**
     * @param positive-int $index
     * @return TestingRequest|TestingSuccessResponse|TestingFailureResponse
     */
    public function get(int $index): CommandInterface
    {
        $command = $this->find($index);

        Assert::assertNotNull($command);

        return $command;
    }

    /**
     * @param \Closure $assert
     * @return $this
     */
    public function each(\Closure $assert): self
    {
        foreach ($this->commands as $command) {
            $assert($command);
        }

        return $this;
    }
}
