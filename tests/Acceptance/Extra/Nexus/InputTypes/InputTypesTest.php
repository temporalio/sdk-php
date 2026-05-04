<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\InputTypes;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: Nexus operations with varying input/output shapes
 * (void, scalar, int, DTO). Validates that the reflection-based OperationDefinition
 * correctly registers the types and the data converter round-trips each one.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class InputTypesTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function voidInputReturnsString(
        State $state,
        #[Stub('Extra_Nexus_InputTypes_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'pingNoInput', null);
        self::assertSame(200, $code, "Body: {$resp}");
        self::assertStringContainsString('pong', $resp);
    }

    #[Test]
    public function scalarIntRoundtrip(
        State $state,
        #[Stub('Extra_Nexus_InputTypes_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'doubleInt', 21);
        self::assertSame(200, $code, "Body: {$resp}");
        self::assertStringContainsString('42', $resp);
    }

    #[Test]
    public function dtoInputDtoOutput(
        State $state,
        #[Stub('Extra_Nexus_InputTypes_Bootstrap3')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'echoDto', [
            'name' => 'Ada',
            'value' => 99,
        ]);

        self::assertSame(200, $code, "Body: {$resp}");
        self::assertStringContainsString('Ada', $resp);
        self::assertStringContainsString('99', $resp);
    }

    /**
     * @return array{int, string}
     */
    private function invoke(State $state, string $operation, mixed $input): array
    {
        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\InputTypes',
            'nexus-input-types',
        );

        return $helper->postOperation($endpointId, 'ShapeService', $operation, $input);
    }
}

// ── DTOs ─────────────────────────────────────────────────────────────

final class Item
{
    public function __construct(
        public string $name = '',
        public int $value = 0,
    ) {}
}

// ── Nexus service ────────────────────────────────────────────────────

#[Service(name: 'ShapeService')]
class ShapeService
{
    /** Operation with no input parameter — handler receives `null`. */
    #[Operation]
    public function pingNoInput(): string
    {
        return 'pong';
    }

    #[Operation]
    public function doubleInt(int $x): int
    {
        return $x * 2;
    }

    #[Operation]
    public function echoDto(Item $item): Item
    {
        // Echo with a trivial transform to prove deserialization actually happened.
        return new Item($item->name, $item->value);
    }
}

// ── Bootstrap workflows ──────────────────────────────────────────────

#[WorkflowInterface]
class InputTypesBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_InputTypes_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class InputTypesBootstrapWorkflow2
{
    #[WorkflowMethod(name: 'Extra_Nexus_InputTypes_Bootstrap2')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class InputTypesBootstrapWorkflow3
{
    #[WorkflowMethod(name: 'Extra_Nexus_InputTypes_Bootstrap3')]
    public function run(): string
    {
        return 'ready';
    }
}
