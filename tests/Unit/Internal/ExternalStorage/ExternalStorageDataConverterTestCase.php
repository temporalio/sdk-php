<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\ExternalStorage;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\DataConverter\RawValue;
use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Exception\DataConverterException;
use Temporal\Internal\ExternalStorage\ExternalStorageDataConverter;
use Temporal\Internal\ExternalStorage\ExternalStorageRunner;
use Temporal\Internal\ExternalStorage\ReferencePayload;
use Temporal\Tests\Unit\Internal\ExternalStorage\Stub\InMemoryStorageDriver;

final class ExternalStorageDataConverterTestCase extends TestCase
{
    public function testReferenceIsRetrievedWhenDecoded(): void
    {
        [$converter, $driver, $reference] = $this->storedValue('payload value');

        self::assertSame([], $driver->retrieveBatchSizes);
        self::assertSame('payload value', $converter->fromPayload($reference, 'string'));
        self::assertSame([1], $driver->retrieveBatchSizes);
    }

    public function testEncodedValuesRetrieveOnlyTheValueThatIsRead(): void
    {
        [$converter, $driver, $reference] = $this->storedValue('payload value');
        $values = EncodedValues::fromPayloads(new Payloads(['payloads' => [$reference]]), $converter);

        self::assertSame([], $driver->retrieveBatchSizes);
        self::assertSame('payload value', $values->getValue(0, 'string'));
        self::assertSame([1], $driver->retrieveBatchSizes);
    }

    public function testRawValueKeepsTheReference(): void
    {
        [$converter, $driver, $reference] = $this->storedValue('payload value');

        $raw = $converter->fromPayload($reference, RawValue::class);

        self::assertInstanceOf(RawValue::class, $raw);
        self::assertTrue(ReferencePayload::isReference($raw->getPayload()));
        self::assertSame([], $driver->retrieveBatchSizes);
    }

    public function testSerializationContextReachesTheDelegate(): void
    {
        [$converter] = $this->storedValue('payload value');
        $context = new WorkflowSerializationContext('default', 'wf-id');

        $withContext = $converter->withSerializationContext($context);

        self::assertNotSame($converter, $withContext);
        self::assertSame($context, $withContext->getSerializationContext());
        self::assertNull($converter->getSerializationContext());
    }

    public function testDataConverterWithoutStorageFailsOnReference(): void
    {
        [, , $reference] = $this->storedValue('payload value');

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('[TMPRL1105]');

        DataConverter::createDefault()->fromPayload($reference, 'string');
    }

    /**
     * @return array{ExternalStorageDataConverter, InMemoryStorageDriver, Payload}
     */
    private function storedValue(string $value): array
    {
        $driver = new InMemoryStorageDriver();
        $runner = new ExternalStorageRunner(
            new ExternalStorage([$driver], payloadSizeThreshold: 0),
            'default',
            new NullLogger(),
        );
        $converter = new ExternalStorageDataConverter(DataConverter::createDefault(), $runner);
        $request = (new StartWorkflowExecutionRequest())->setInput(
            new Payloads(['payloads' => [$converter->toPayload($value)]]),
        );

        return [$converter, $driver, $runner->store($request)->getInput()->getPayloads()[0]];
    }
}
