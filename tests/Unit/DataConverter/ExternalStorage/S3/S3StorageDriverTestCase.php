<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter\ExternalStorage\S3;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\S3\S3ClientInterface;
use Temporal\DataConverter\ExternalStorage\S3\S3StorageDriver;
use Temporal\DataConverter\ExternalStorage\StorageDriverActivityInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverTargetInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;
use Temporal\Exception\DataConverterException;
use Temporal\Tests\Unit\DataConverter\ExternalStorage\Stub\CountingLimiter;

final class S3StorageDriverTestCase extends TestCase
{
    private const GO_PAYLOAD = 'ChYKCGVuY29kaW5nEgpqc29uL3BsYWluEgciaGVsbG8i';
    private const GO_HASH = '1b8435174847de88db3a8c5a764295d62add152743b3a9a730796a4c090caf8c';

    public static function provideGoClaims(): iterable
    {
        yield 'workflow with escaped values' => [
            new StorageDriverWorkflowInfo('default', 'wf/1?é', 'run-1', 'My Workflow'),
            'v0/ns/default/wt/My%20Workflow/wi/wf%2F1%3F%C3%A9/ri/run-1/d/sha256/' . self::GO_HASH,
        ];
        yield 'workflow with empty values' => [
            new StorageDriverWorkflowInfo('ns', 'wf'),
            'v0/ns/ns/wt/null/wi/wf/ri/null/d/sha256/' . self::GO_HASH,
        ];
        yield 'standalone activity' => [
            new StorageDriverActivityInfo('ns', 'a/1', 'run-2', 'Act Type'),
            'v0/ns/ns/at/Act%20Type/ai/a%2F1/ri/run-2/d/sha256/' . self::GO_HASH,
        ];
        yield 'no target' => [
            null,
            'v0/d/sha256/' . self::GO_HASH,
        ];
    }

    #[DataProvider('provideGoClaims')]
    public function testClaimMatchesTheGoDriver(?StorageDriverTargetInfo $target, string $key): void
    {
        $client = new InMemoryS3Client();
        $payload = new Payload();
        $payload->mergeFromString(\base64_decode(self::GO_PAYLOAD));

        $claims = (new S3StorageDriver($client, 'bucket'))->store(self::storeContext($target), [$payload]);

        self::assertEquals([new StorageDriverClaim([
            'bucket' => 'bucket',
            'key' => $key,
            'hash_algorithm' => 'sha256',
            'hash_value' => self::GO_HASH,
        ])], $claims);
        self::assertSame(\base64_decode(self::GO_PAYLOAD), $client->objects["bucket/$key"]);
    }

    private static function storeContext(?StorageDriverTargetInfo $target): StorageDriverStoreContext
    {
        return new StorageDriverStoreContext($target, 'default', new CountingLimiter());
    }

    private static function retrieveContext(): StorageDriverRetrieveContext
    {
        return new StorageDriverRetrieveContext(new CountingLimiter());
    }

    public function testObjectWrittenByTheGoDriverIsRetrieved(): void
    {
        $key = 'v0/d/sha256/' . self::GO_HASH;
        $client = new InMemoryS3Client();
        $client->objects["bucket/$key"] = \base64_decode(self::GO_PAYLOAD);

        $payloads = (new S3StorageDriver($client, 'bucket'))->retrieve(self::retrieveContext(), [new StorageDriverClaim([
            'bucket' => 'bucket',
            'key' => $key,
            'hash_algorithm' => 'sha256',
            'hash_value' => self::GO_HASH,
        ])]);

        self::assertSame('"hello"', $payloads[0]->getData());
        self::assertSame('json/plain', $payloads[0]->getMetadata()['encoding']);
    }

    public function testExistingObjectIsNotUploadedAgain(): void
    {
        $client = new InMemoryS3Client();
        $driver = new S3StorageDriver($client, 'bucket');
        $payload = (new Payload())->setData('same');

        $driver->store(self::storeContext(null), [$payload]);
        $driver->store(self::storeContext(null), [$payload]);

        self::assertSame(1, $client->puts);
    }

    public function testBucketCanBeResolvedPerPayload(): void
    {
        $client = new InMemoryS3Client();
        $driver = new S3StorageDriver(
            $client,
            static fn(StorageDriverStoreContext $context, Payload $payload): string => 'bucket-' . $payload->getData(),
        );

        $claims = $driver->store(self::storeContext(null), [(new Payload())->setData('a')]);

        self::assertSame('bucket-a', $claims[0]->claimData['bucket']);
    }

    public function testTamperedObjectFailsTheIntegrityCheck(): void
    {
        $client = new InMemoryS3Client();
        $driver = new S3StorageDriver($client, 'bucket');
        [$claim] = $driver->store(self::storeContext(null), [(new Payload())->setData('original')]);
        $client->objects['bucket/' . $claim->claimData['key']] = (new Payload())->setData('tampered')->serializeToString();

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('Integrity check failed');

        $driver->retrieve(self::retrieveContext(), [$claim]);
    }

    public function testClaimWithoutKeyFails(): void
    {
        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage("Claim missing field 'key'.");

        (new S3StorageDriver(new InMemoryS3Client(), 'bucket'))->retrieve(self::retrieveContext(), [new StorageDriverClaim(['bucket' => 'b'])]);
    }

    public function testEveryRequestTakesAPermit(): void
    {
        $limiter = new CountingLimiter();
        $client = new InMemoryS3Client();
        $driver = new S3StorageDriver($client, 'bucket');

        $claims = $driver->store(
            new StorageDriverStoreContext(null, 'default', $limiter),
            [(new Payload())->setData('a'), (new Payload())->setData('b')],
        );
        $driver->retrieve(new StorageDriverRetrieveContext($limiter), $claims);

        self::assertSame(4, $limiter->permits);
    }

    public function testOversizedPayloadIsRejectedBeforeUpload(): void
    {
        $client = new InMemoryS3Client();

        try {
            (new S3StorageDriver($client, 'bucket', maxPayloadSize: 10))->store(
                self::storeContext(null),
                [(new Payload())->setData('small'), (new Payload())->setData(\str_repeat('x', 100))],
            );
            self::fail('The oversized payload is accepted.');
        } catch (DataConverterException $e) {
            self::assertStringContainsString('exceeds maximum 10', $e->getMessage());
        }

        self::assertSame(0, $client->puts);
    }
}

final class InMemoryS3Client implements S3ClientInterface
{
    /** @var array<string, string> */
    public array $objects = [];

    public int $puts = 0;

    public function putObject(string $bucket, string $key, string $data): void
    {
        ++$this->puts;
        $this->objects["$bucket/$key"] = $data;
    }

    public function objectExists(string $bucket, string $key): bool
    {
        return isset($this->objects["$bucket/$key"]);
    }

    public function getObject(string $bucket, string $key): string
    {
        return $this->objects["$bucket/$key"];
    }
}
