<?php

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Interceptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;

#[CoversClass(StartOperationInput::class)]
final class StartOperationInputTest extends TestCase
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
        self::assertSame($input->startDetails, $copy->startDetails);
        self::assertSame($input->input, $copy->input);
    }

    public function testWithOverridesOperationContextOnly(): void
    {
        $input = $this->makeInput();
        $newContext = new OperationContext('other-service', 'other-operation', $this->env);

        $copy = $input->with(operationContext: $newContext);

        self::assertSame($newContext, $copy->operationContext);
        self::assertSame($input->startDetails, $copy->startDetails);
        self::assertSame($input->input, $copy->input);
    }

    public function testWithOverridesStartDetailsOnly(): void
    {
        $input = $this->makeInput();
        $newDetails = new OperationStartDetails('other-request-id');

        $copy = $input->with(startDetails: $newDetails);

        self::assertSame($input->operationContext, $copy->operationContext);
        self::assertSame($newDetails, $copy->startDetails);
        self::assertSame($input->input, $copy->input);
    }

    public function testWithOverridesInputOnly(): void
    {
        $input = $this->makeInput();

        $copy = $input->with(input: 'changed');

        self::assertSame($input->operationContext, $copy->operationContext);
        self::assertSame($input->startDetails, $copy->startDetails);
        self::assertSame('changed', $copy->input);
    }

    private function makeInput(): StartOperationInput
    {
        return new StartOperationInput(
            new OperationContext('service', 'operation', $this->env),
            new OperationStartDetails('request-id'),
            'original-input',
        );
    }
}
