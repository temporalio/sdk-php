<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\TestCase;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteNexusOperationInput;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;

/**
 * @group unit
 * @group nexus
 */
final class WorkflowContextNexusOptionsTestCase extends TestCase
{
    public function testInterceptorEndpointAndServiceRewritesAreHonored(): void
    {
        $options = NexusOperationOptions::new()
            ->withEndpoint('orig-ep')
            ->withService('OrderService')
            ->withCancellationType(NexusOperationCancellationType::Abandon);
        $input = $this->makeInput($options)->with(endpoint: 'new-ep', service: 'NewService');

        $effective = $this->deriveOptions($input);

        self::assertSame('new-ep', $effective->endpoint);
        self::assertSame('NewService', $effective->service);
        self::assertSame(NexusOperationCancellationType::Abandon, $effective->cancellationType);
    }

    public function testUntouchedInputReturnsOriginalOptionsInstance(): void
    {
        $options = NexusOperationOptions::new()
            ->withEndpoint('orig-ep')
            ->withService('OrderService');

        self::assertSame($options, $this->deriveOptions($this->makeInput($options)));
    }

    private function makeInput(NexusOperationOptions $options): ExecuteNexusOperationInput
    {
        return new ExecuteNexusOperationInput(
            $options->endpoint,
            $options->service,
            'place-order',
            [],
            $options,
            null,
        );
    }

    private function deriveOptions(ExecuteNexusOperationInput $input): NexusOperationOptions
    {
        $method = new \ReflectionMethod(WorkflowContext::class, 'effectiveNexusOptions');

        return $method->invoke(null, $input);
    }
}
