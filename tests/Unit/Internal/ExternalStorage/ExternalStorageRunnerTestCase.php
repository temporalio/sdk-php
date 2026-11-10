<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\ExternalStorage;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest\Operation;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\DataConverter\ExternalStorage\StorageDriverActivityInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverInterface;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverSelectContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;
use Temporal\Exception\DataConverterException;
use Temporal\Internal\ExternalStorage\ExternalStorageRunner;
use Temporal\Internal\ExternalStorage\ReferencePayload;
use Temporal\Tests\Unit\Internal\ExternalStorage\Stub\InMemoryStorageDriver;

final class ExternalStorageRunnerTestCase extends TestCase
{
    private const THRESHOLD = 1024;

    /** @var list<string> */
    private array $warnings = [];

    public function testSmallPayloadsStayInline(): void
    {
        $driver = new InMemoryStorageDriver();
        $request = $this->startRequest(self::payload(10));

        $result = $this->runner($driver)->store($request);

        self::assertSame($request, $result);
        self::assertSame([], $driver->objects);
    }

    public function testLargePayloadIsReplacedWithMarkedReference(): void
    {
        $driver = new InMemoryStorageDriver();
        $original = self::payload(2000);

        $result = $this->runner($driver)->store($this->startRequest($original));

        self::assertInstanceOf(StartWorkflowExecutionRequest::class, $result);
        $reference = $result->getInput()->getPayloads()[0];
        $metadata = \iterator_to_array($reference->getMetadata());
        self::assertEquals([
            EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_PROTOBUF_JSON,
            EncodingKeys::METADATA_MESSAGE_TYPE => ReferencePayload::MESSAGE_TYPE,
            ReferencePayload::MARKER => 'true',
        ], $metadata);
        self::assertSame('{"driverName":"memory","claimData":{"id":"0"}}', $reference->getData());
        self::assertSame(\strlen($original->serializeToString()), (int) $reference->getExternalPayloads()[0]->getSizeBytes());
        self::assertSame($original->serializeToString(), $driver->objects['0']);
    }

    public function testOriginalRequestIsNotModified(): void
    {
        $original = self::payload(2000);
        $request = $this->startRequest($original);
        $before = $request->serializeToString();

        $this->runner(new InMemoryStorageDriver())->store($request);

        self::assertSame($before, $request->serializeToString());
        self::assertSame(\str_repeat('x', 2000), $original->getData());
    }

    public function testStoreContextCarriesTheTargetAndTheNamespace(): void
    {
        $driver = new InMemoryStorageDriver();

        $this->runner($driver)->store($this->startRequest(self::payload(2000)));

        self::assertEquals(new StorageDriverWorkflowInfo('default', 'wf-id', '', 'WfType'), $driver->storeContexts[0]->target);
        self::assertSame('client-ns', $driver->storeContexts[0]->namespace);
    }

    public function testSignalTargetUsesTheWorkflowExecution(): void
    {
        $request = (new SignalWorkflowExecutionRequest())
            ->setNamespace('ns')
            ->setWorkflowExecution(new WorkflowExecution(['workflow_id' => 'wf-id', 'run_id' => 'run-id']))
            ->setInput(self::payloads(2000));

        self::assertEquals(new StorageDriverWorkflowInfo('ns', 'wf-id', 'run-id'), $this->storeTarget($request));
    }

    public function testMultiOperationTargetUsesTheStartOperation(): void
    {
        $request = new ExecuteMultiOperationRequest([
            'operations' => [new Operation(['start_workflow' => $this->startRequest(self::payload(2000))])],
        ]);

        self::assertEquals(new StorageDriverWorkflowInfo('default', 'wf-id', '', 'WfType'), $this->storeTarget($request));
    }

    public function testStandaloneActivityStartUsesAnActivityTarget(): void
    {
        $request = (new StartActivityExecutionRequest())
            ->setNamespace('ns')
            ->setActivityId('act-id')
            ->setActivityType(new ActivityType(['name' => 'ActType']))
            ->setInput(self::payloads(2000));

        self::assertEquals(new StorageDriverActivityInfo('ns', 'act-id', '', 'ActType'), $this->storeTarget($request));
    }

    public function testActivityResultByIdUsesTheWorkflowThatScheduledIt(): void
    {
        $request = (new RespondActivityTaskCompletedByIdRequest())
            ->setNamespace('ns')
            ->setWorkflowId('wf-id')
            ->setRunId('run-id')
            ->setActivityId('act-id')
            ->setResult(self::payloads(2000));

        self::assertEquals(new StorageDriverWorkflowInfo('ns', 'wf-id', 'run-id'), $this->storeTarget($request));
    }

    public function testStandaloneActivityResultByIdUsesTheActivity(): void
    {
        $request = (new RespondActivityTaskCompletedByIdRequest())
            ->setNamespace('ns')
            ->setRunId('activity-run-id')
            ->setActivityId('act-id')
            ->setResult(self::payloads(2000));

        self::assertEquals(new StorageDriverActivityInfo('ns', 'act-id', 'activity-run-id'), $this->storeTarget($request));
    }

    public function testFailureDetailsAreStored(): void
    {
        $driver = new InMemoryStorageDriver();
        $request = (new RespondActivityTaskFailedByIdRequest())
            ->setNamespace('ns')
            ->setWorkflowId('wf-id')
            ->setRunId('run-id')
            ->setFailure(
                (new Failure())->setCause(
                    (new Failure())->setApplicationFailureInfo(
                        (new ApplicationFailureInfo())->setDetails(self::payloads(2000)),
                    ),
                ),
            );

        $this->runner($driver)->store($request);

        self::assertCount(1, $driver->objects);
    }

    public function testMemoAndHeaderPayloadsAreStoredInOneBatch(): void
    {
        $driver = new InMemoryStorageDriver();
        $request = $this->startRequest(self::payload(2000))
            ->setMemo(new Memo(['fields' => ['memo' => self::payload(2000)]]))
            ->setHeader(new Header(['fields' => ['header' => self::payload(2000)]]));

        $this->runner($driver)->store($request);

        self::assertCount(3, $driver->objects);
        self::assertCount(1, $driver->storeContexts);
    }

    public function testSearchAttributesAreNotStored(): void
    {
        $driver = new InMemoryStorageDriver();
        $request = $this->startRequest(self::payload(10))
            ->setSearchAttributes(new SearchAttributes(['indexed_fields' => ['attr' => self::payload(2000)]]));

        $result = $this->runner($driver)->store($request);

        self::assertSame($request, $result);
        self::assertSame([], $driver->objects);
    }

    public function testZeroThresholdStoresEveryPayload(): void
    {
        $driver = new InMemoryStorageDriver();

        $this->runner($driver, threshold: 0)->store($this->startRequest(self::payload(1)));

        self::assertCount(1, $driver->objects);
    }

    public function testExistingReferenceIsNotStoredAgain(): void
    {
        $driver = new InMemoryStorageDriver();
        $runner = $this->runner($driver, threshold: 0);
        $reference = $runner->store($this->startRequest(self::payload(1)))->getInput()->getPayloads()[0];

        $result = $runner->store($this->startRequest($reference));

        self::assertSame($reference->serializeToString(), $result->getInput()->getPayloads()[0]->serializeToString());
        self::assertCount(1, $driver->objects);
    }

    public function testSelectorGetsTheSelectContextAndRoutesPayloads(): void
    {
        $small = new InMemoryStorageDriver('small');
        $large = new InMemoryStorageDriver('large');
        $contexts = [];
        $runner = new ExternalStorageRunner(
            new ExternalStorage(
                [$small, $large],
                static function (StorageDriverSelectContext $context, Payload $payload) use ($small, $large, &$contexts): ?StorageDriverInterface {
                    $contexts[] = $context;
                    return match (true) {
                        \strlen($payload->getData()) > 3000 => $large,
                        \strlen($payload->getData()) > 1500 => $small,
                        default => null,
                    };
                },
                self::THRESHOLD,
            ),
            'client-ns',
            new NullLogger(),
        );
        $request = (new StartWorkflowExecutionRequest())->setInput(new Payloads([
            'payloads' => [self::payload(4000), self::payload(2000), self::payload(1200)],
        ]));

        $result = $runner->store($request);

        self::assertCount(1, $large->objects);
        self::assertCount(1, $small->objects);
        self::assertSame(\str_repeat('x', 1200), $result->getInput()->getPayloads()[2]->getData());
        self::assertSame('client-ns', $contexts[0]->namespace);
    }

    public function testSelectorReturningUnregisteredDriverFails(): void
    {
        $runner = new ExternalStorageRunner(
            new ExternalStorage([new InMemoryStorageDriver()], static fn() => new InMemoryStorageDriver(), self::THRESHOLD),
            'client-ns',
            new NullLogger(),
        );

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage("not registered with this external storage: 'memory'");

        $runner->store($this->startRequest(self::payload(2000)));
    }

    public function testDriverReturningWrongClaimCountFails(): void
    {
        $driver = new InMemoryStorageDriver(claims: static fn(): array => []);

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage("Storage driver 'memory' returned 0 claims for 1 payloads.");

        $this->runner($driver)->store($this->startRequest(self::payload(2000)));
    }

    public function testDriverReturningNullClaimFails(): void
    {
        $driver = new InMemoryStorageDriver(claims: static fn(): array => [null]);

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage("Storage driver 'memory' returned an invalid element at position 0 of the claims.");

        $this->runner($driver)->store($this->startRequest(self::payload(2000)));
    }

    public function testDriverWithoutPermitsIsReported(): void
    {
        $this->runner(new InMemoryStorageDriver(usePermits: false))->store($this->startRequest(self::payload(2000)));

        self::assertCount(1, $this->warnings);
        self::assertStringContainsString("Storage driver 'memory' completed an operation without taking a permit", $this->warnings[0]);
    }

    public function testDriverWithPermitsIsNotReported(): void
    {
        $this->runner(new InMemoryStorageDriver())->store($this->startRequest(self::payload(2000)));

        self::assertSame([], $this->warnings);
    }

    public function testNestedPermitsBeyondTheMessageBudgetFail(): void
    {
        $driver = new class implements StorageDriverInterface {
            public function getName(): string
            {
                return 'nested';
            }

            public function getType(): string
            {
                return 'test.nested';
            }

            public function store(StorageDriverStoreContext $context, array $payloads): array
            {
                return $context->limiter->permit(
                    $payloads[0],
                    static fn(): array => $context->limiter->permit(
                        $payloads[0],
                        static fn(): array => [new StorageDriverClaim(['id' => '0'])],
                    ),
                );
            }

            public function retrieve(StorageDriverRetrieveContext $context, array $claims): array
            {
                return [];
            }
        };
        $runner = new ExternalStorageRunner(
            new ExternalStorage([$driver], payloadSizeThreshold: 0, maxOperationsPerMessage: 1),
            'client-ns',
            new NullLogger(),
        );

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('budget of 1 operations is exhausted');

        $runner->store($this->startRequest(self::payload(1)));
    }

    public function testStoredPayloadIsRetrieved(): void
    {
        $driver = new InMemoryStorageDriver();
        $runner = $this->runner($driver);
        $original = (new Payload())
            ->setMetadata([EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_JSON])
            ->setData(\str_repeat('y', 2000));
        $reference = $runner->store($this->startRequest($original))->getInput()->getPayloads()[0];

        $retrieved = $runner->retrieve($reference);

        self::assertSame($original->serializeToString(), $retrieved->serializeToString());
        self::assertSame([1], $driver->retrieveBatchSizes);
    }

    public function testInlinePayloadIsNotRetrieved(): void
    {
        $driver = new InMemoryStorageDriver();
        $payload = self::payload(10);

        self::assertSame($payload, $this->runner($driver)->retrieve($payload));
        self::assertSame([], $driver->retrieveBatchSizes);
    }

    public function testUnmarkedReferenceWrittenByAnotherSdkIsRetrieved(): void
    {
        $driver = new InMemoryStorageDriver();
        $driver->objects['7'] = self::payload(5)->serializeToString();
        $reference = (new Payload())
            ->setMetadata([
                EncodingKeys::METADATA_ENCODING_KEY => 'json/protobuf',
                EncodingKeys::METADATA_MESSAGE_TYPE => 'temporal.api.sdk.v1.ExternalStorageReference',
            ])
            ->setData('{"driverName":"memory","claimData":{"id":"7"},"futureField":true}');

        self::assertSame('xxxxx', $this->runner($driver)->retrieve($reference)->getData());
    }

    public function testCodecEncodedReferenceFails(): void
    {
        $reference = (new Payload())
            ->setMetadata([EncodingKeys::METADATA_ENCODING_KEY => 'binary/encrypted', ReferencePayload::MARKER => 'true'])
            ->setData('encrypted');

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('encoded by a payload codec');

        $this->runner(new InMemoryStorageDriver())->retrieve($reference);
    }

    public function testReferenceOfUnknownDriverFails(): void
    {
        $stored = $this->runner(new InMemoryStorageDriver('other'))->store($this->startRequest(self::payload(2000)));

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage("No storage driver registered with name 'other'.");

        $this->runner(new InMemoryStorageDriver())->retrieve($stored->getInput()->getPayloads()[0]);
    }

    public function testNonProtobufRequestPassesThrough(): void
    {
        $value = (object) ['result' => true];

        self::assertSame($value, $this->runner(new InMemoryStorageDriver(), threshold: 0)->store($value));
    }

    protected function setUp(): void
    {
        $this->warnings = [];
        parent::setUp();
    }

    private static function payload(int $size): Payload
    {
        return (new Payload())->setData(\str_repeat('x', $size));
    }

    private static function payloads(int $size): Payloads
    {
        return new Payloads(['payloads' => [self::payload($size)]]);
    }

    private function storeTarget(object $request): mixed
    {
        $driver = new InMemoryStorageDriver();
        $this->runner($driver)->store($request);

        return $driver->storeContexts[0]->target;
    }

    private function runner(InMemoryStorageDriver $driver, int $threshold = self::THRESHOLD): ExternalStorageRunner
    {
        return new ExternalStorageRunner(
            new ExternalStorage([$driver], payloadSizeThreshold: $threshold),
            'client-ns',
            new class($this->warnings) extends AbstractLogger {
                public function __construct(private array &$warnings) {}

                public function log($level, \Stringable|string $message, array $context = []): void
                {
                    $this->warnings[] = (string) $message;
                }
            },
        );
    }

    private function startRequest(Payload ...$payloads): StartWorkflowExecutionRequest
    {
        return (new StartWorkflowExecutionRequest())
            ->setNamespace('default')
            ->setWorkflowId('wf-id')
            ->setWorkflowType(new WorkflowType(['name' => 'WfType']))
            ->setInput(new Payloads(['payloads' => $payloads]));
    }
}
