<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use React\Promise\Deferred;
use Spiral\Attributes\AttributeReader;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Transport\Router\GetWorkerInfo;
use Temporal\Plugin\PluginRegistry;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\Declaration\Fixture\SimpleWorkflow;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithDynamic;
use Temporal\Tests\Unit\Framework\Requests\GetWorkerInfo as Request;
use Temporal\Worker\ServiceCredentials;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

final class GetWorkerInfoTestCase extends AbstractUnit
{
    public function testDynamicWorkflowFlagIsAdvertised(): void
    {
        $reader = new WorkflowReader(new AttributeReader());
        $worker = $this->createMock(WorkerInterface::class);
        $options = WorkerOptions::new();

        $worker->method('getID')->willReturn('test-queue');
        $worker->method('getOptions')->willReturn($options);
        $worker->method('getWorkflows')->willReturn([
            $reader->fromClass(WorkflowWithDynamic::class),
            $reader->fromClass(SimpleWorkflow::class),
        ]);
        $worker->method('getActivities')->willReturn([]);

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('marshal')
            ->with($options)
            ->willReturn([]);

        $router = new GetWorkerInfo(
            new ArrayRepository([$worker]),
            $marshaller,
            ServiceCredentials::create(),
            new PluginRegistry(),
        );
        $resolver = new Deferred();
        $response = null;
        $resolver->promise()->then(static function (EncodedValues $values) use (&$response): void {
            $response = $values->getValues();
        });

        $router->handle(new Request(), [], $resolver);

        self::assertNotNull($response);
        self::assertSame(true, $response[0]['Workflows'][0]['dynamic']);
        self::assertSame(false, $response[0]['Workflows'][1]['dynamic']);
    }
}
