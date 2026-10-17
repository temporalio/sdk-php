<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Nexus\Exception\ErrorType;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Tests\Nexus\Fixtures\ServiceHandler\ManualTokenService;
use Temporal\Tests\Nexus\Support\BindNexusService;
use Temporal\Tests\Nexus\Support\EncodesValues;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;

#[CoversClass(ServiceHandler::class)]
final class ManualTokenOperationTest extends TestCase
{
    use BindNexusService;
    use EncodesValues;
    use ExceptionAssertions;

    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testStartReturnsManualTokenWithoutWorkflowClient(): void
    {
        $result = $this->handler()->startOperation(
            $this->context('startExternal'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('job-42'),
            null,
            new NexusOperationContext(),
        );

        self::assertSame('ext-job-42', $result->info->token);
    }

    public function testCancelRoutesToHandlerCancelMethod(): void
    {
        $service = new ManualTokenService();
        $handler = $this->handler($service);

        $handler->cancelOperation(
            $this->context('startExternal'),
            new OperationCancelDetails(operationToken: 'ext-job-42'),
            null,
            new NexusOperationContext(),
        );

        self::assertSame('ext-job-42', $service->externalJobHandler->cancelledToken);
    }

    public function testCancelThrowingNotImplementedSurfacesErrorType(): void
    {
        $e = self::assertThrown(HandlerException::class, fn() => $this->handler()->cancelOperation(
            $this->context('startUncancellable'),
            new OperationCancelDetails(operationToken: 'ext-fixed-1'),
            null,
            new NexusOperationContext(),
        ));

        self::assertSame(ErrorType::NotImplemented, $e->errorType);
    }

    public function testStartRejectsNonRunningOperationInfo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must report a running operation');

        $this->handler()->startOperation(
            $this->context('startAlreadyFinished'),
            new OperationStartDetails(requestId: 'r2'),
            self::encode('job-43'),
            null,
            new NexusOperationContext(),
        );
    }

    private function handler(?ManualTokenService $service = null): ServiceHandler
    {
        return ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService($service ?? new ManualTokenService())],
        );
    }

    private function context(string $operation): OperationContext
    {
        return new OperationContext(service: 'ManualTokenService', operation: $operation, env: $this->env);
    }
}
