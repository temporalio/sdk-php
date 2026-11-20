<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Temporal\Testing\Downloader;
use Temporal\Testing\SystemInfo;

final class DownloaderTestCase extends TestCase
{
    /**
     * @psalm-suppress DeprecatedClass
     */
    public function testSkipsNonArchiveReleaseAssets(): void
    {
        $systemInfo = SystemInfo::detect();
        $archiveName = "temporal-test-server_1.41.1_{$systemInfo->platform}_{$systemInfo->os}.tar.gz";
        $archiveUrl = 'https://github.com/temporalio/sdk-java/releases/download/v1.41.1/' . $archiveName;
        $release = new MockResponse(\json_encode([
            'assets' => [
                ['name' => 'SHA256SUMS'],
                ['name' => $archiveName, 'browser_download_url' => $archiveUrl],
            ],
        ], \JSON_THROW_ON_ERROR));

        $error = new TransportException('Stop after selecting the archive');
        $httpClient = new MockHttpClient([
            $release,
            static function (string $method, string $url) use ($archiveUrl, $error): never {
                self::assertSame('GET', $method);
                self::assertSame($archiveUrl, $url);

                throw $error;
            },
        ]);

        $this->expectExceptionObject($error);
        \set_error_handler(static function (int $severity, string $message): never {
            self::fail($message);
        }, \E_WARNING);

        try {
            (new Downloader(new Filesystem(), $httpClient))->download($systemInfo);
        } finally {
            \restore_error_handler();
        }
    }
}
