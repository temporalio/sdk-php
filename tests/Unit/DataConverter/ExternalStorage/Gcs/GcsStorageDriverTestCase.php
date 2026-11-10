<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter\ExternalStorage\Gcs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\Gcs\GcsClientInterface;
use Temporal\DataConverter\ExternalStorage\Gcs\GcsStorageDriver;
use Temporal\DataConverter\ExternalStorage\StorageDriverActivityInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverTargetInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;
use Temporal\Exception\DataConverterException;
use Temporal\Tests\Unit\DataConverter\ExternalStorage\Stub\CountingLimiter;

final class GcsStorageDriverTestCase extends TestCase
{
    private const GO_PAYLOAD = 'ChYKCGVuY29kaW5nEgpqc29uL3BsYWluEgciaGVsbG8i';
    private const GO_HASH = '1b8435174847de88db3a8c5a764295d62add152743b3a9a730796a4c090caf8c';

    public static function provideGoObjectNames(): iterable
    {
        yield 'workflow with unsafe and unicode characters' => [
            new StorageDriverWorkflowInfo('default', "wf/1?é#[x]%\n\u{0085}", 'run-1', 'My Workflow'),
            'v0/ns/default/wt/My Workflow/wi/wf%2F1%3Fé%23%5Bx%5D%25%0A%C2%85/ri/run-1/d/sha256/' . self::GO_HASH,
        ];
        yield 'dot segments' => [
            new StorageDriverWorkflowInfo('ns', '..', '.'),
            'v0/ns/ns/wt/null/wi/%2E%2E/ri/%2E/d/sha256/' . self::GO_HASH,
        ];
        yield 'standalone activity' => [
            new StorageDriverActivityInfo('ns', 'a/1', 'run-2', 'Act:Type'),
            'v0/ns/ns/at/Act%3AType/ai/a%2F1/ri/run-2/d/sha256/' . self::GO_HASH,
        ];
        yield 'name longer than 1024 bytes keeps only the namespace' => [
            new StorageDriverWorkflowInfo('ns', \str_repeat('w', 1100)),
            'v0/ns/ns/d/sha256/' . self::GO_HASH,
        ];
        yield 'no target' => [
            null,
            'v0/d/sha256/' . self::GO_HASH,
        ];
    }

    #[DataProvider('provideGoObjectNames')]
    public function testClaimMatchesTheGoDriver(?StorageDriverTargetInfo $target, string $objectName): void
    {
        $client = new InMemoryGcsClient();
        $payload = new Payload();
        $payload->mergeFromString(\base64_decode(self::GO_PAYLOAD));

        $claims = (new GcsStorageDriver($client, 'bucket'))->store(self::storeContext($target), [$payload]);

        self::assertEquals([new StorageDriverClaim([
            'bucket' => 'bucket',
            'object_name' => $objectName,
            'key' => $objectName,
            'hash_algorithm' => 'sha256',
            'hash_value' => self::GO_HASH,
        ])], $claims);
        self::assertSame(\base64_decode(self::GO_PAYLOAD), $client->objects["bucket/$objectName"]);
    }

    public function testLegacyClaimWithOnlyTheKeyIsRetrieved(): void
    {
        $client = new InMemoryGcsClient();
        $client->objects['bucket/v0/d/sha256/' . self::GO_HASH] = \base64_decode(self::GO_PAYLOAD);

        $payloads = (new GcsStorageDriver($client, 'bucket'))->retrieve(self::retrieveContext(), [new StorageDriverClaim([
            'bucket' => 'bucket',
            'key' => 'v0/d/sha256/' . self::GO_HASH,
            'hash_algorithm' => 'sha256',
            'hash_value' => self::GO_HASH,
        ])]);

        self::assertSame('"hello"', $payloads[0]->getData());
    }

    public function testTamperedObjectFailsTheIntegrityCheck(): void
    {
        $client = new InMemoryGcsClient();
        $driver = new GcsStorageDriver($client, 'bucket');
        [$claim] = $driver->store(self::storeContext(null), [(new Payload())->setData('original')]);
        $client->objects['bucket/' . $claim->claimData['object_name']] = (new Payload())->setData('tampered')->serializeToString();

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('Integrity check failed');

        $driver->retrieve(self::retrieveContext(), [$claim]);
    }

    public function testExistingObjectIsNotUploadedAgain(): void
    {
        $client = new InMemoryGcsClient();
        $driver = new GcsStorageDriver($client, 'bucket');

        $driver->store(self::storeContext(null), [(new Payload())->setData('same')]);
        $driver->store(self::storeContext(null), [(new Payload())->setData('same')]);

        self::assertSame(1, $client->puts);
    }

    private static function storeContext(?StorageDriverTargetInfo $target): StorageDriverStoreContext
    {
        return new StorageDriverStoreContext($target, 'default', new CountingLimiter());
    }

    private static function retrieveContext(): StorageDriverRetrieveContext
    {
        return new StorageDriverRetrieveContext(new CountingLimiter());
    }
}

final class InMemoryGcsClient implements GcsClientInterface
{
    /** @var array<string, string> */
    public array $objects = [];

    public int $puts = 0;

    public function putObject(string $bucket, string $objectName, string $data): void
    {
        ++$this->puts;
        $this->objects["$bucket/$objectName"] = $data;
    }

    public function objectExists(string $bucket, string $objectName): bool
    {
        return isset($this->objects["$bucket/$objectName"]);
    }

    public function getObject(string $bucket, string $objectName): string
    {
        return $this->objects["$bucket/$objectName"];
    }
}
