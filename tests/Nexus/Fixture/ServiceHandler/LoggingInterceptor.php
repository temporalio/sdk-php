<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceHandler;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationMiddlewareInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;

/**
 * Test middleware that records each operation it sees.
 */
final class LoggingInterceptor implements OperationMiddlewareInterface
{
    /** @var list<string> */
    private array $operations = [];

    /** @return list<string> */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function intercept(
        OperationContext $context,
        OperationHandlerInterface $next,
    ): OperationHandlerInterface {
        return new class ($this, $next) implements OperationHandlerInterface {
            public function __construct(
                private readonly LoggingInterceptor $sink,
                private readonly OperationHandlerInterface $next,
            ) {}

            public function start(
                OperationContext $context,
                OperationStartDetails $details,
                mixed $param,
            ): OperationStartResult {
                $this->sink->record($context->operation);
                return $this->next->start($context, $details, $param);
            }

            public function cancel(
                OperationContext $context,
                OperationCancelDetails $details,
            ): void {
                $this->sink->record($context->operation);
                $this->next->cancel($context, $details);
            }

            public static function sync(callable $function): self
            {
                throw new \LogicException('not a factory handler');
            }
        };
    }

    /** @internal */
    public function record(string $operation): void
    {
        $this->operations[] = $operation;
    }
}
