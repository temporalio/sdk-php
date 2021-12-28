<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server;

use Carbon\Carbon;
use Temporal\Tests\Unit\Framework\CommandBatchMock;
use Temporal\Tests\Unit\Framework\Expectation\ExpectationInterface;
use Temporal\Tests\Unit\Framework\Server\CommandHandler\AffectsServerStateHandler;
use Temporal\Tests\Unit\Framework\Server\CommandHandler\CommandHandlerFactory;
use Temporal\Worker\Transport\Command\CommandInterface;

final class ServerMock
{
    private CommandHandlerFactory $commandHandlerFactory;
    private Carbon $currentTime;
    private array $queue = [];

    /** @var ExpectationInterface[] */
    private array $expectations = [];

    public function __construct(CommandHandlerFactory $commandHandlerFactory)
    {
        $this->commandHandlerFactory = $commandHandlerFactory;
        $this->currentTime = Carbon::now();
    }

    public function hasEmptyQueue(): bool
    {
        return $this->queue === [];
    }

    public function getBatch(): CommandBatchMock
    {
        $context = ['taskQueue' => 'default', 'tickTime' => $this->currentTime->toAtomString()];
        $batch = new CommandBatchMock($this->queue, $context);
        $this->queue = [];

        return $batch;
    }

    public function addCommand(CommandInterface ...$commands): void
    {
        $this->queue = array_merge($this->queue, $commands);
    }

    public function handleCommand(CommandInterface $command): ?CommandInterface
    {
        $expectation = $this->checkExpectation($command);
        if ($expectation !== null) {
            return $expectation->run($command);
        }

        $handler = $this->commandHandlerFactory->getHandler($command);
        if ($handler instanceof AffectsServerStateHandler) {
            $handler->updateState($command, $this->currentTime);
        }

        return $handler->handle($command);
    }

    private function checkExpectation(CommandInterface $command): ?ExpectationInterface
    {
        foreach ($this->expectations as $index => $expectation) {
            if ($expectation->matches($command)) {
                unset($this->expectations[$index]);
                return $expectation;
            }
        }

        return null;
    }

    public function checkWaitingExpectations(): void
    {
        foreach ($this->expectations as $expectation) {
            $expectation->fail();
        }
    }

    public function expect(ExpectationInterface $expectation): void
    {
        $this->expectations[] = $expectation;
    }
}
