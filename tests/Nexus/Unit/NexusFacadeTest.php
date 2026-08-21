<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Tests\Nexus\Support\BindNexusService;
use Temporal\Tests\Nexus\Support\EncodesValues;
use Temporal\Tests\Nexus\Support\ExceptionAssertions;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[Service(name: 'FacadeProbeService')]
final class FacadeProbeService
{
    public ?OperationCancelDetails $capturedCancelDetails = null;

    #[Operation]
    public function probeCancelDetails(string $input): string
    {
        Nexus::getCancelDetails();
        return 'unreachable';
    }

    #[Operation]
    public function probeWorkflowClient(string $input): string
    {
        Nexus::getWorkflowClient();
        return 'unreachable';
    }

    #[AsyncOperation(output: 'string', input: 'string')]
    public function startJob(): FacadeJobHandler
    {
        return new FacadeJobHandler($this);
    }
}

final class FacadeJobHandler implements OperationHandlerInterface
{
    public function __construct(
        private readonly FacadeProbeService $service,
    ) {}

    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        return OperationStartResult::async(new OperationInfo("job-{$param}", OperationState::Running));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        $this->service->capturedCancelDetails = Nexus::getCancelDetails();
    }
}

#[CoversClass(Nexus::class)]
final class NexusFacadeTest extends TestCase
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

    public function testGetCancelDetailsOutsideAnyDispatchThrows(): void
    {
        $e = self::assertThrown(\LogicException::class, static fn() => Nexus::getCancelDetails());

        self::assertStringContainsString('only inside a Nexus operation handler', $e->getMessage());
    }

    public function testGetCancelDetailsDuringStartDispatchThrows(): void
    {
        $handler = $this->handler();

        $e = self::assertThrown(\LogicException::class, fn() => $handler->startOperation(
            $this->context('probeCancelDetails'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('x'),
            null,
            new NexusOperationContext(),
        ));

        self::assertStringContainsString('outside a cancel-operation dispatch', $e->getMessage());
    }

    public function testGetCancelDetailsAvailableInsideCancelDispatch(): void
    {
        $service = new FacadeProbeService();
        $this->handler($service)->cancelOperation(
            $this->context('startJob'),
            new OperationCancelDetails(operationToken: 'job-42'),
            null,
            new NexusOperationContext(),
        );

        self::assertNotNull($service->capturedCancelDetails);
        self::assertSame('job-42', $service->capturedCancelDetails->operationToken);
    }

    public function testGetWorkflowClientWithoutClientThrows(): void
    {
        $handler = $this->handler();

        $e = self::assertThrown(\LogicException::class, fn() => $handler->startOperation(
            $this->context('probeWorkflowClient'),
            new OperationStartDetails(requestId: 'r1'),
            self::encode('x'),
            null,
            new NexusOperationContext(),
        ));

        self::assertStringContainsString('Nexus::getWorkflowClient() requires a WorkflowClient', $e->getMessage());
    }

    private function handler(?FacadeProbeService $service = null): ServiceHandler
    {
        return ServiceHandler::create(
            dataConverter: self::dataConverter(),
            instances: [self::bindNexusService($service ?? new FacadeProbeService())],
        );
    }

    private function context(string $operation): OperationContext
    {
        return new OperationContext(service: 'FacadeProbeService', operation: $operation, env: $this->env);
    }
}
