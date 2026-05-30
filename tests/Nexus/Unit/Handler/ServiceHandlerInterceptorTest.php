<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\NexusOperationInboundCallsInterceptorTrait;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Tests\Nexus\Fixtures\Service\GreetingService;
use Temporal\Tests\Nexus\Support\BindNexusService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class ServiceHandlerInterceptorTest extends TestCase
{
    use BindNexusService;

    public function testMultipleInterceptorsAreAppliedInRegistrationOrder(): void
    {
        $log = [];
        $record = static function (string $name) use (&$log): NexusOperationInboundCallsInterceptor {
            return new class($name, $log) implements NexusOperationInboundCallsInterceptor {
                use NexusOperationInboundCallsInterceptorTrait;

                /**
                 * @param list<string> $log
                 */
                public function __construct(
                    private readonly string $name,
                    private array &$log,
                ) {}

                public function startOperation(
                    StartOperationInput $input,
                    callable $next,
                ): OperationStartResult {
                    $this->log[] = "enter:{$this->name}";
                    $result = $next($input);
                    $this->log[] = "exit:{$this->name}";
                    return $result;
                }
            };
        };

        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new GreetingService(static fn($n) => "g-{$n}"))],
            interceptorProvider: new SimplePipelineProvider([$record('A'), $record('B'), $record('C')]),
        );

        $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('User'),
        );

        // First registered interceptor is the outermost.
        self::assertSame(
            ['enter:A', 'enter:B', 'enter:C', 'exit:C', 'exit:B', 'exit:A'],
            $log,
        );
    }

    public function testInterceptorCanOverrideHandlerResult(): void
    {
        $overriding = new class implements NexusOperationInboundCallsInterceptor {
            use NexusOperationInboundCallsInterceptorTrait;

            public function startOperation(
                StartOperationInput $input,
                callable $next,
            ): OperationStartResult {
                return OperationStartResult::sync("rewritten-{$input->input}");
            }
        };

        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new GreetingService(static fn($n) => "g-{$n}"))],
            interceptorProvider: new SimplePipelineProvider([$overriding]),
        );

        $result = $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('Alice'),
        );

        self::assertSame('rewritten-Alice', $result->value->getValue(0, 'string'));
    }

    public function testInterceptorExceptionPropagates(): void
    {
        $exploding = new class implements NexusOperationInboundCallsInterceptor {
            use NexusOperationInboundCallsInterceptorTrait;

            public function startOperation(
                StartOperationInput $input,
                callable $next,
            ): OperationStartResult {
                throw HandlerException::create(ErrorType::Unauthorized, 'blocked');
            }
        };

        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new GreetingService(static fn($n) => "g-{$n}"))],
            interceptorProvider: new SimplePipelineProvider([$exploding]),
        );

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('blocked');
        $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello1'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('X'),
        );
    }

    public function testInterceptorCanSwallowHandlerException(): void
    {
        $swallowing = new class implements NexusOperationInboundCallsInterceptor {
            use NexusOperationInboundCallsInterceptorTrait;

            public function startOperation(
                StartOperationInput $input,
                callable $next,
            ): OperationStartResult {
                try {
                    return $next($input);
                } catch (HandlerException) {
                    return OperationStartResult::sync('fallback');
                }
            }
        };

        // The injected apiClient is invoked by the async sayHello2 path; we make it throw.
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new GreetingService(
                static fn(string $n): string => throw HandlerException::create(ErrorType::Internal, 'boom'),
            ))],
            interceptorProvider: new SimplePipelineProvider([$swallowing]),
        );

        $result = $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello2'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('X'),
        );

        self::assertSame('fallback', $result->value->getValue(0, 'string'));
    }

    public function testCancelInterceptorReceivesContext(): void
    {
        $seen = [];
        $observer = new class($seen) implements NexusOperationInboundCallsInterceptor {
            use NexusOperationInboundCallsInterceptorTrait;

            /**
             * @param list<string> $seen
             */
            public function __construct(private array &$seen) {}

            public function cancelOperation(CancelOperationInput $input, callable $next): void
            {
                $this->seen[] = "{$input->operationContext->operation}:{$input->cancelDetails->operationToken}";
                $next($input);
            }
        };

        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService(new GreetingService(static fn($n) => "g-{$n}"))],
            interceptorProvider: new SimplePipelineProvider([$observer]),
        );

        $started = $handler->startOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello2'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('User'),
        );

        $token = $started->info->token;
        self::assertNotNull($token);

        $handler->cancelOperation(
            new OperationContext(service: 'GreetingServiceInterface', operation: 'sayHello2'),
            new \Temporal\Nexus\Handler\OperationCancelDetails(operationToken: $token),
        );

        self::assertSame(["sayHello2:{$token}"], $seen);
    }

    private static function dataConverter(): DataConverterInterface
    {
        return DataConverter::createDefault();
    }

    private static function encode(mixed $value): EncodedValues
    {
        return EncodedValues::fromValues([$value], self::dataConverter());
    }
}
