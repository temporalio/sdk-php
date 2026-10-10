<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Api\Enums\V1\TimeoutType;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(TemporalFailure::class)]
#[CoversClass(ApplicationFailure::class)]
#[CoversClass(CanceledFailure::class)]
#[CoversClass(TimeoutFailure::class)]
final class TemporalFailureSerializationContextTestCase extends AbstractUnit
{
    public function testApplicationFailureBindsDetails(): void
    {
        $context = new WorkflowSerializationContext('ns', 'workflow-id');
        $failure = new ApplicationFailure('boom', 'Boom', true, EncodedValues::fromValues(['detail']));

        $failure->setSerializationContext($context);
        self::assertEquals($context, $failure->getDetails()->getSerializationContext());
        self::assertSame(['detail'], $failure->getDetails()->getValues());
    }

    public function testCanceledFailureBindsDetails(): void
    {
        $context = new WorkflowSerializationContext('ns', 'workflow-id');
        $failure = new CanceledFailure('canceled', EncodedValues::fromValues(['detail']));

        $failure->setSerializationContext($context);

        self::assertEquals($context, $failure->getDetails()->getSerializationContext());
    }

    public function testTimeoutFailureBindsLastHeartbeatDetails(): void
    {
        $context = new WorkflowSerializationContext('ns', 'workflow-id');
        $failure = new TimeoutFailure('timeout', EncodedValues::fromValues(['beat']), TimeoutType::TIMEOUT_TYPE_HEARTBEAT);

        $failure->setSerializationContext($context);

        self::assertEquals($context, $failure->getLastHeartbeatDetails()->getSerializationContext());
    }

    public function testContextReachesCauseThroughWrappingFailure(): void
    {
        $context = new WorkflowSerializationContext('ns', 'workflow-id');
        $cause = new ApplicationFailure('boom', 'Boom', true, EncodedValues::fromValues(['detail']));
        $failure = new ActivityFailure(1, 2, 'DoWork', 'activity-id', 0, 'identity', $cause);

        $failure->setSerializationContext($context);

        self::assertEquals($context, $cause->getDetails()->getSerializationContext());
    }

    public function testContextReachesFailureBehindPlainException(): void
    {
        $context = new WorkflowSerializationContext('ns', 'workflow-id');
        $cause = new ApplicationFailure('inner', 'Inner', true, EncodedValues::fromValues(['detail']));
        $failure = new ApplicationFailure('outer', 'Outer', true, null, new \RuntimeException('plain', 0, $cause));

        $failure->setSerializationContext($context);

        self::assertEquals($context, $cause->getDetails()->getSerializationContext());
    }

    public function testAlreadyBoundDetailsKeepTheirContext(): void
    {
        $activityContext = new WorkflowSerializationContext('ns', 'activity-side');
        $failure = new ApplicationFailure('boom', 'Boom', true, EncodedValues::fromValues(['detail']));
        $failure->setSerializationContext($activityContext);

        $failure->setSerializationContext(new WorkflowSerializationContext('ns', 'workflow-side'));

        self::assertSame($activityContext, $failure->getDetails()->getSerializationContext());
    }

    public function testCustomDetailsWithoutContextSupportAreKept(): void
    {
        $details = $this->createMock(ValuesInterface::class);
        $failure = new ApplicationFailure('boom', 'Boom', true, $details);

        $failure->setSerializationContext(new WorkflowSerializationContext('ns', 'workflow-id'));

        self::assertSame($details, $failure->getDetails());
    }

    public function testBindSerializationContextFindsFailureBehindPlainException(): void
    {
        $context = new WorkflowSerializationContext('ns', 'workflow-id');
        $failure = new ApplicationFailure('inner', 'Inner', true, EncodedValues::fromValues(['detail']));

        TemporalFailure::bindSerializationContext(new \RuntimeException('plain', 0, $failure), $context);

        self::assertEquals($context, $failure->getDetails()->getSerializationContext());
    }
}
