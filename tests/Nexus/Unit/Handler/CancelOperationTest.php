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
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Tests\Nexus\Fixtures\Service\GreetingService;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\AuthInterceptor;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\CancelSignaturesService;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\LoggingInterceptor;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\VoidService;
use Temporal\Tests\Nexus\Support\BindNexusService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHandler::class)]
final class CancelOperationTest extends TestCase
{
    use BindNexusService;

    public function testCancelUnrecognizedService(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [
                self::bindNexusService(new VoidService()),
            ],
        );

        $this->expectException(HandlerException::class);
        $handler->cancelOperation(
            new OperationContext(service: 'NonExistent', operation: 'op'),
            new OperationCancelDetails(operationToken: 'token'),
        );
    }

    public function testCancelUnrecognizedOperation(): void
    {
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [
                self::bindNexusService(new VoidService()),
            ],
        );

        $this->expectException(HandlerException::class);
        $handler->cancelOperation(
            new OperationContext(service: 'VoidServiceInterface', operation: 'nonExistent'),
            new OperationCancelDetails(operationToken: 'token'),
        );
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
                headers: [AuthInterceptor::AUTH_HEADER => $authToken],
            ),
            new OperationStartDetails(requestId: 'r1'),
            EncodedValues::fromValues(['SomeUser'], self::dataConverter()),
        );

        $token = $result->info->token;
        self::assertNotNull($token);

        // Cancel it.
        $handler->cancelOperation(
            new OperationContext(
                service: 'GreetingServiceInterface',
                operation: 'sayHello2',
                headers: [AuthInterceptor::AUTH_HEADER => $authToken],
            ),
            new OperationCancelDetails(operationToken: $token),
        );

        // Logging interceptor saw both start and cancel.
        self::assertSame(['sayHello2', 'sayHello2'], $loggingInterceptor->getOperations());
    }

    public function testCancelLegacyStringSignature(): void
    {
        $service = new CancelSignaturesService();
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService($service)],
        );

        $handler->cancelOperation(
            new OperationContext(service: 'CancelSignaturesServiceInterface', operation: 'legacy'),
            new OperationCancelDetails(operationToken: 'token-legacy'),
        );

        self::assertSame('token-legacy', $service->cancelCalls['legacy']);
    }

    public function testCancelContextAndDetailsSignature(): void
    {
        $service = new CancelSignaturesService();
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService($service)],
        );

        $details = new OperationCancelDetails(operationToken: 'token-cd');
        $handler->cancelOperation(
            new OperationContext(service: 'CancelSignaturesServiceInterface', operation: 'contextAndDetails'),
            $details,
        );

        [$context, $passedDetails] = $service->cancelCalls['contextAndDetails'];
        self::assertInstanceOf(OperationContext::class, $context);
        self::assertSame('CancelSignaturesServiceInterface', $context->service);
        self::assertSame('contextAndDetails', $context->operation);
        self::assertSame($details, $passedDetails);
        self::assertSame('token-cd', $passedDetails->operationToken);
    }

    public function testCancelReversedSignatureResolvesByType(): void
    {
        $service = new CancelSignaturesService();
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService($service)],
        );

        $details = new OperationCancelDetails(operationToken: 'token-rev');
        $handler->cancelOperation(
            new OperationContext(service: 'CancelSignaturesServiceInterface', operation: 'reversed'),
            $details,
        );

        [$passedDetails, $context] = $service->cancelCalls['reversed'];
        self::assertInstanceOf(OperationCancelDetails::class, $passedDetails);
        self::assertInstanceOf(OperationContext::class, $context);
        self::assertSame($details, $passedDetails);
        self::assertSame('reversed', $context->operation);
    }

    public function testCancelNoArgsSignature(): void
    {
        $service = new CancelSignaturesService();
        $handler = ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService($service)],
        );

        $handler->cancelOperation(
            new OperationContext(service: 'CancelSignaturesServiceInterface', operation: 'noArgs'),
            new OperationCancelDetails(operationToken: 'token-noargs'),
        );

        self::assertTrue($service->cancelCalls['noArgs']);
    }

    private static function dataConverter(): DataConverterInterface
    {
        return DataConverter::createDefault();
    }
}
