<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\NexusOperationContext;

use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Tests\Nexus\Fixtures\Service\GreetingService;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\AuthInterceptor;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\LoggingInterceptor;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\VoidService;
use Temporal\Tests\Nexus\Support\BindNexusService;
use Temporal\Tests\Nexus\Support\EncodesValues;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use Temporal\Tests\Nexus\Support\MocksAsyncWorkflowClient;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class CancelOperationTest extends TestCase
{
    use BindNexusService;
    use EncodesValues;
    use ExceptionAssertions;
    use MocksAsyncWorkflowClient;

    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testCancelUnrecognizedService(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [
                self::bindNexusService(new VoidService()),
            ],
        );

        $e = self::assertThrown(HandlerException::class, fn() => $handler->cancelOperation(
            new OperationContext(service: 'NonExistent', operation: 'op', env: $this->env),
            new OperationCancelDetails(operationToken: 'token'),
            null,
            new NexusOperationContext(),
        ));

        self::assertSame(ErrorType::NotFound, $e->errorType);
        self::assertStringContainsString("Unrecognized service 'NonExistent'", $e->getMessage());
    }

    public function testCancelUnrecognizedOperation(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [
                self::bindNexusService(new VoidService()),
            ],
        );

        $e = self::assertThrown(HandlerException::class, fn() => $handler->cancelOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'nonExistent', env: $this->env),
            new OperationCancelDetails(operationToken: 'token'),
            null,
            new NexusOperationContext(),
        ));

        self::assertSame(ErrorType::NotFound, $e->errorType);
        self::assertStringContainsString("has no operation 'nonExistent'", $e->getMessage());
    }

    public function testCancelWithInterceptor(): void
    {
        $apiClient = static fn(string $name): string => "greeting-{$name}";
        $authToken = 'auth-token';
        $loggingInterceptor = new LoggingInterceptor();

        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [
                self::bindNexusService(new GreetingService($apiClient)),
            ],
            interceptorProvider: new SimplePipelineProvider([
                new AuthInterceptor($authToken),
                $loggingInterceptor,
            ]),
        );

        // Start an async operation first.
        $result = $handler->startOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello2',
                env: $this->env,
                headers: [AuthInterceptor::AUTH_HEADER => $authToken],
            ),
            new OperationStartDetails(requestId: 'r1'),
            EncodedValues::fromValues(['SomeUser'], self::dataConverter()),
            $this->asyncClient(),
            new NexusOperationContext('test-ns', 'test-tq'),
        );

        $token = $result->info->token;
        self::assertNotNull($token);

        // Cancel it.
        $handler->cancelOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello2',
                env: $this->env,
                headers: [AuthInterceptor::AUTH_HEADER => $authToken],
            ),
            new OperationCancelDetails(operationToken: $token),
            $this->asyncClient(),
            new NexusOperationContext('test-ns', 'test-tq'),
        );

        // Logging interceptor saw both start and cancel.
        self::assertSame(['sayHello2', 'sayHello2'], $loggingInterceptor->getOperations());
    }
}
