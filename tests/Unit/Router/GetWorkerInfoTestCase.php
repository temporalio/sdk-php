<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use React\Promise\Deferred;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\ProtoConverter;
use Temporal\DataConverter\ProtoJsonConverter;
use Temporal\DataConverter\Type;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Transport\Router\GetWorkerInfo;
use Temporal\Plugin\PluginRegistry;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\Framework\Requests\GetWorkerInfo as GetWorkerInfoRequest;
use Temporal\Worker\ServiceCredentials;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

final class GetWorkerInfoTestCase extends AbstractUnit
{
    public function testWorkerInfoStaysPlainUnderCustomConverter(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->method('getID')->willReturn('my-tq');
        $worker->method('getOptions')->willReturn(WorkerOptions::new());
        $worker->method('getWorkflows')->willReturn([]);
        $worker->method('getActivities')->willReturn([]);

        $queues = new ArrayRepository();
        $queues->add($worker);

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('marshal')->willReturn([]);

        $router = new GetWorkerInfo($queues, $marshaller, ServiceCredentials::create(), new PluginRegistry([]));

        $captured = null;
        $resolver = new Deferred();
        $resolver->promise()->then(static function ($value) use (&$captured): void {
            $captured = $value;
        });
        $router->handle(new GetWorkerInfoRequest(), [], $resolver);

        self::assertInstanceOf(EncodedValues::class, $captured);

        $captured->setDataConverter(new DataConverter(
            new NullConverter(),
            new BinaryConverter(),
            new ProtoJsonConverter(),
            new ProtoConverter(),
            new EncryptEverythingConverter(),
        ));

        $payload = $captured->toPayloads()->getPayloads()[0];

        self::assertSame('json/plain', $payload->getMetadata()['encoding']);
    }
}

final class EncryptEverythingConverter implements PayloadConverterInterface
{
    public function getEncodingType(): string
    {
        return 'binary/encrypted';
    }

    public function toPayload($value): ?Payload
    {
        return (new Payload())
            ->setMetadata(['encoding' => 'binary/encrypted'])
            ->setData('---' . \json_encode($value) . '---');
    }

    public function fromPayload(Payload $payload, Type $type): mixed
    {
        return \json_decode(\substr($payload->getData(), 3, -3), true);
    }
}
