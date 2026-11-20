<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter\ExternalStorage;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\FileStorageDriver;
use Temporal\DataConverter\ExternalStorage\InMemoryStorageDriver;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;
use Temporal\Exception\DataConverterException;
use Temporal\Tests\Unit\DataConverter\ExternalStorage\Stub\CountingLimiter;

final class LocalStorageDriversTestCase extends TestCase
{
    private string $directory;

    public function testInMemoryDriverRoundTrip(): void
    {
        $driver = new InMemoryStorageDriver();
        $payload = (new Payload())->setData('value');

        $claims = $driver->store(self::storeContext(), [$payload]);

        self::assertSame(['hash_algorithm' => 'sha256', 'hash_value' => \hash('sha256', $payload->serializeToString())], $claims[0]->claimData);
        self::assertSame('value', $driver->retrieve(self::retrieveContext(), $claims)[0]->getData());
    }

    public function testInMemoryDriverFailsOnUnknownObject(): void
    {
        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage("No stored object with hash 'missing'.");

        (new InMemoryStorageDriver())->retrieve(
            self::retrieveContext(),
            [new StorageDriverClaim(['hash_algorithm' => 'sha256', 'hash_value' => 'missing'])],
        );
    }

    public function testFileDriverStoresUnderTheTargetHierarchy(): void
    {
        $driver = new FileStorageDriver($this->directory);
        $payload = (new Payload())->setData('value');
        $digest = \hash('sha256', $payload->serializeToString());

        [$claim] = $driver->store(
            new StorageDriverStoreContext(new StorageDriverWorkflowInfo('ns', '../wf 1', 'run'), 'ns', new CountingLimiter()),
            [$payload],
        );

        $path = "v0/ns/ns/wt/null/wi/..%2Fwf%201/ri/run/d/sha256/$digest";
        self::assertSame(['path' => $path, 'hash_algorithm' => 'sha256', 'hash_value' => $digest], $claim->claimData);
        self::assertFileExists("$this->directory/$path");
        self::assertSame('value', (new FileStorageDriver($this->directory))->retrieve(self::retrieveContext(), [$claim])[0]->getData());
    }

    public function testFileDriverEncodesDotSegments(): void
    {
        [$claim] = (new FileStorageDriver($this->directory))->store(
            new StorageDriverStoreContext(new StorageDriverWorkflowInfo('ns', '..', '.'), 'ns', new CountingLimiter()),
            [(new Payload())->setData('value')],
        );

        self::assertStringStartsWith('v0/ns/ns/wt/null/wi/%2E%2E/ri/%2E/d/sha256/', $claim->claimData['path']);
    }

    public function testFileDriverRejectsTraversalInTheClaim(): void
    {
        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('Invalid stored object path');

        (new FileStorageDriver($this->directory))->retrieve(self::retrieveContext(), [new StorageDriverClaim([
            'path' => '../outside',
            'hash_algorithm' => 'sha256',
            'hash_value' => 'x',
        ])]);
    }

    public function testFileDriverFailsOnTamperedFile(): void
    {
        $driver = new FileStorageDriver($this->directory);
        [$claim] = $driver->store(self::storeContext(), [(new Payload())->setData('original')]);
        \file_put_contents("$this->directory/{$claim->claimData['path']}", (new Payload())->setData('tampered')->serializeToString());

        $this->expectException(DataConverterException::class);
        $this->expectExceptionMessage('Integrity check failed');

        $driver->retrieve(self::retrieveContext(), [$claim]);
    }

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/temporal-file-driver-' . \bin2hex(\random_bytes(6));
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->directory)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
            }
            \rmdir($this->directory);
        }
        parent::tearDown();
    }

    private static function storeContext(): StorageDriverStoreContext
    {
        return new StorageDriverStoreContext(null, 'default', new CountingLimiter());
    }

    private static function retrieveContext(): StorageDriverRetrieveContext
    {
        return new StorageDriverRetrieveContext(new CountingLimiter());
    }
}
