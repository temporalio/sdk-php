<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Server\CommandHandler;

use Temporal\Worker\Transport\Command\CommandInterface;

final class CommandHandlerFactory
{
    /** @var CommandHandlerInterface[] */
    private array $handlers;

    public function __construct(CommandHandlerInterface ...$handlers)
    {
        $this->handlers = $handlers;
    }

    public function getHandler(CommandInterface $request): CommandHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($request)) {
                return $handler;
            }
        }

        throw new \LogicException("Unsupported request: " . get_class($request));
    }

    public static function create(): self
    {
        return new self(
            new ExecuteActivityHandler(),
            new NewTimerHandler(),
            new CompleteWorkflowHandler(),
            new FailureResponseHandler(),
            new SuccessResponseHandler(),
            new GetVersionHandler(),
        );
    }
}
