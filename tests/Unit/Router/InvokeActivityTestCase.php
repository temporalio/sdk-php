<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use React\Promise\Deferred;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Internal\Activity\ActivityContext;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router\InvokeActivity;
use Temporal\Tests\Unit\UnitTestCase;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Tests\Unit\Framework\Requests\InvokeActivity as Request;

final class InvokeActivityTestCase extends UnitTestCase
{
    private ServiceContainer $services;
    private InvokeActivity $router;

    protected function setUp(): void
    {
        $rpc = $this->createMock(RPCConnectionInterface::class);

        $dataConverter = $this->createMock(DataConverterInterface::class);
        $marshaller = $this->createMock(MarshallerInterface::class);
        $this->activityContext = new ActivityContext($rpc, $dataConverter, EncodedValues::empty());
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturn($this->activityContext);

        $this->services = new ServiceContainer(
            $this->createMock(LoopInterface::class),
            $this->createMock(EnvironmentInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(ReaderInterface::class),
            $this->createMock(QueueInterface::class),
            $marshaller,
            $dataConverter,
            $this->createMock(ExceptionInterceptorInterface::class),
        );
        $activityReader = new ActivityReader(new SelectiveReader([new AnnotationReader(), new AttributeReader()]));
        foreach ($activityReader->fromClass(DummyActivity::class) as $proto) {
            $this->services->activities->add($proto);
        }

        $this->router = new InvokeActivity($this->services, $rpc);

        parent::setUp();
    }


    public function testFinalizerIsCalledOnSuccessActivityInvocation(): void
    {
        $finalizerWasCalled = false;
        $this->services->activities->addFinalizer(
            function () use (&$finalizerWasCalled) {
                $finalizerWasCalled = true;
            }
        );

        $this->activityContext->getInfo()->type->name = 'DummyActivityDoNothing';
        $request = new Request('DummyActivityDoNothing', EncodedValues::fromValues([]));
        $this->router->handle($request, [], new Deferred());
        $this->assertTrue($finalizerWasCalled);
    }

    public function testFinalizerIsCalledOnFailedActivityInvocation(): void
    {
        $finalizerWasCalled = false;
        $this->services->activities->addFinalizer(
            function () use (&$finalizerWasCalled) {
                $finalizerWasCalled = true;
            }
        );

        $this->activityContext->getInfo()->type->name = 'DummyActivityDoFail';
        $request = new Request('DummyActivityDoFail', EncodedValues::fromValues([]));
        $this->router->handle($request, [], new Deferred());
        $this->assertTrue($finalizerWasCalled);
    }
}
