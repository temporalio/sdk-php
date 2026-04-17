<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\Header;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Workflow\NexusOperationStub;
use Temporal\Workflow\NexusOperationOptions;

/**
 * @group unit
 * @group nexus
 */
final class NexusOperationStubTestCase extends TestCase
{
    public function testStartRejectsEmptyEndpoint(): void
    {
        $stub = $this->makeStub(NexusOperationOptions::new());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus endpoint is empty');

        $stub->start('someOp');
    }

    public function testStartRejectsEmptyService(): void
    {
        $stub = $this->makeStub(
            NexusOperationOptions::new()->withEndpoint('ep'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus service is empty');

        $stub->start('someOp');
    }

    public function testStartRejectsEmptyOperationName(): void
    {
        $stub = $this->makeStub(
            NexusOperationOptions::new()
                ->withEndpoint('ep')
                ->withService('svc'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus operation name must be a non-empty string');

        $stub->start('');
    }

    private function makeStub(NexusOperationOptions $options): NexusOperationStub
    {
        /** @var MarshallerInterface<array> $marshaller */
        $marshaller = $this->createStub(MarshallerInterface::class);
        return new NexusOperationStub($marshaller, $options, Header::empty());
    }
}
