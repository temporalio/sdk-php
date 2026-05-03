<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\HandlerInputContent;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationMiddlewareInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Handler\ServiceHandler;
use Temporal\Nexus\Handler\ServiceImplInstance;
use Temporal\Tests\Nexus\Fixture\Impl\GreetingServiceImpl;
use Temporal\Tests\Nexus\Fixture\Serializer\StringOnlySerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerMiddlewareTest extends TestCase
{
    public function testMultipleMiddlewaresAreAppliedInReverseOrder(): void
    {
        $log = [];
        $record = static function (string $name) use (&$log): OperationMiddlewareInterface {
            return new class($name, $log) implements OperationMiddlewareInterface {
                /** @param list<string> $log */
                public function __construct(
                    private readonly string $name,
                    private array &$log,
                ) {}

                public function intercept(
                    OperationContext $context,
                    OperationHandlerInterface $next,
                ): OperationHandlerInterface {
                    $this->log[] = "wrap:{$this->name}";
                    $log = &$this->log;
                    $name = $this->name;

                    return new class($next, $log, $name) implements OperationHandlerInterface {
                        /** @param list<string> $log */
                        public function __construct(
                            private readonly OperationHandlerInterface $next,
                            private array &$log,
                            private readonly string $name,
                        ) {}

                        public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
                        {
                            $this->log[] = "enter:{$this->name}";
                            $result = $this->next->start($context, $details, $param);
                            $this->log[] = "exit:{$this->name}";
                            return $result;
                        }

                        public function cancel(OperationContext $context, OperationCancelDetails $details): void
                        {
                            $this->next->cancel($context, $details);
                        }

                    };
                }
            };
        };

        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new GreetingServiceImpl(fn($n) => "g-{$n}"))],
            middlewares: [$record('A'), $record('B'), $record('C')],
        );

        $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('User'),
        );

        // Wrapping happens in reverse order (C first wraps the root handler, then B, then A).
        // At runtime the outermost is A (first in the list), so enter order is A,B,C; exit is C,B,A.
        self::assertSame(
            ['wrap:C', 'wrap:B', 'wrap:A', 'enter:A', 'enter:B', 'enter:C', 'exit:C', 'exit:B', 'exit:A'],
            $log,
        );
    }

    public function testMiddlewareCanModifyHandlerReturn(): void
    {
        $overriding = new class implements OperationMiddlewareInterface {
            public function intercept(OperationContext $context, OperationHandlerInterface $next): OperationHandlerInterface
            {
                return new class implements OperationHandlerInterface {
                    public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
                    {
                        return OperationStartResult::sync("rewritten-{$param}");
                    }
                    public function cancel(OperationContext $context, OperationCancelDetails $details): void {}
                };
            }
        };

        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new GreetingServiceImpl(fn($n) => "g-{$n}"))],
            middlewares: [$overriding],
        );

        $result = $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('Alice'),
        );

        self::assertSame('rewritten-Alice', $result->value->data);
    }

    public function testMiddlewareExceptionPropagates(): void
    {
        $exploding = new class implements OperationMiddlewareInterface {
            public function intercept(OperationContext $context, OperationHandlerInterface $next): OperationHandlerInterface
            {
                throw HandlerException::create(ErrorType::Unauthorized, 'blocked');
            }
        };

        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new GreetingServiceImpl(fn($n) => "g-{$n}"))],
            middlewares: [$exploding],
        );

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('blocked');
        $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('X'),
        );
    }

    public function testMiddlewareCanSwallowHandlerException(): void
    {
        $alwaysFailing = new class implements OperationHandlerInterface {
            public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
            {
                throw HandlerException::create(ErrorType::Internal, 'boom');
            }
            public function cancel(OperationContext $context, OperationCancelDetails $details): void {}
        };

        $swallowing = new class($alwaysFailing) implements OperationMiddlewareInterface {
            public function __construct(private readonly OperationHandlerInterface $inner) {}

            public function intercept(OperationContext $context, OperationHandlerInterface $next): OperationHandlerInterface
            {
                $inner = $this->inner;
                return new class($inner) implements OperationHandlerInterface {
                    public function __construct(private readonly OperationHandlerInterface $inner) {}
                    public function start(OperationContext $context, OperationStartDetails $details, mixed $param): OperationStartResult
                    {
                        try {
                            return $this->inner->start($context, $details, $param);
                        } catch (HandlerException) {
                            return OperationStartResult::sync('fallback');
                        }
                    }
                    public function cancel(OperationContext $context, OperationCancelDetails $details): void {}
                };
            }
        };

        $handler = ServiceHandler::create(
            serializer: new StringOnlySerializer(),
            instances: [ServiceImplInstance::fromInstance(new GreetingServiceImpl(fn($n) => "g-{$n}"))],
            middlewares: [$swallowing],
        );

        $result = $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            new HandlerInputContent('X'),
        );

        self::assertSame('fallback', $result->value->data);
    }
}
