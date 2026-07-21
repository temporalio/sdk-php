<?php

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Interceptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;

#[CoversClass(CancelOperationInput::class)]
final class CancelOperationInputTest extends TestCase
{
    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
    }

    public function testWithReturnsNewInstanceKeepingValues(): void
    {
        $input = $this->makeInput();
        $copy = $input->with();

        self::assertNotSame($input, $copy);
        self::assertSame($input->operationContext, $copy->operationContext);
        self::assertSame($input->cancelDetails, $copy->cancelDetails);
    }

    public function testWithOverridesOperationContextOnly(): void
    {
        $input = $this->makeInput();
        $newContext = new OperationContext('other-service', 'other-operation', $this->env);

        $copy = $input->with(operationContext: $newContext);

        self::assertSame($newContext, $copy->operationContext);
        self::assertSame($input->cancelDetails, $copy->cancelDetails);
    }

    public function testWithOverridesCancelDetailsOnly(): void
    {
        $input = $this->makeInput();
        $newDetails = new OperationCancelDetails('other-token');

        $copy = $input->with(cancelDetails: $newDetails);

        self::assertSame($input->operationContext, $copy->operationContext);
        self::assertSame($newDetails, $copy->cancelDetails);
    }

    private function makeInput(): CancelOperationInput
    {
        return new CancelOperationInput(
            new OperationContext('service', 'operation', $this->env),
            new OperationCancelDetails('operation-token'),
        );
    }
}
