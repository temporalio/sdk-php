<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use DateTimeImmutable;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Queue\QueueInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Router\InvokeActivity;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\Transport\RPCConnectionInterface;

final class InvokeActivityHeartbeatContextTestCase extends AbstractUnit
{
    private const NAMESPACE = 'test-namespace';
    private const TASK_QUEUE = 'test-task-queue';
    private const ACTIVITY = 'HeartbeatDetailsActivity.ReadSignature';

    public function testHeartbeatDetailsAreDecodedWithTheActivityContext(): void
    {
        $signature = $this->runActivity();

        $this->assertSame(
            \sprintf('act|%s|%s|%s', self::NAMESPACE, self::ACTIVITY, self::TASK_QUEUE),
            $signature,
        );
    }

    private function runActivity(): string
    {
        $converter = new DataConverter(new ContextSignatureConverter());
        $services = $this->createServices($converter);
        $router = new InvokeActivity($services, $this->createMock(RPCConnectionInterface::class), new SimplePipelineProvider());

        $resolver = new Deferred();
        $router->handle($this->createRequest($converter), [], $resolver);

        $result = null;
        $resolver->promise()->then(static function (ValuesInterface $values) use (&$result): void {
            $result = $values->getValue(0);
        });

        return $result;
    }

    private function createRequest(DataConverter $converter): ServerRequest
    {
        $options = [
            'name' => self::ACTIVITY,
            'heartbeatDetails' => 1,
            'info' => [
                'TaskToken' => \base64_encode('token'),
                'ActivityType' => ['Name' => self::ACTIVITY],
                'ActivityID' => '1',
                'WorkflowNamespace' => self::NAMESPACE,
                'TaskQueue' => self::TASK_QUEUE,
                'Attempt' => 2,
            ],
        ];

        return new ServerRequest(
            'InvokeActivity',
            new TickInfo(new DateTimeImmutable()),
            $options,
            EncodedValues::fromValues(['input', 'heartbeat'], $converter),
        );
    }

    private function createServices(DataConverter $converter): ServiceContainer
    {
        $services = new ServiceContainer(
            $this->createMock(LoopInterface::class),
            $this->createMock(EnvironmentInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(ReaderInterface::class),
            $this->createMock(QueueInterface::class),
            new Marshaller(new AttributeMapperFactory(new AttributeReader())),
            $converter,
            $this->createMock(ExceptionInterceptorInterface::class),
            new SimplePipelineProvider(),
            new NullLogger(),
        );

        $reader = new ActivityReader(new SelectiveReader([new AnnotationReader(), new AttributeReader()]));
        foreach ($reader->fromClass(HeartbeatDetailsActivity::class) as $proto) {
            $services->activities->add($proto);
        }

        return $services;
    }
}
