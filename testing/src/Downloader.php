<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Downloader
{
    private const LATEST_JAVA_SDK_RELEASE = 'https://api.github.com/repos/temporalio/sdk-java/releases/latest';
    private Filesystem $filesystem;
    private HttpClientInterface $httpClient;

    public function __construct(Filesystem $filesystem, HttpClientInterface $httpClient)
    {
        $this->filesystem = $filesystem;
        $this->httpClient = $httpClient;
    }

    private function findAsset(array $assets, string $systemPlatform, string $systemArch): array
    {
        foreach ($assets as $asset) {
            preg_match('/^temporal-test-server_[^_]+_([^_]+)_([^.]+)\.(?:zip|tar.gz)$/', $asset['name'], $match);
            [, $assetPlatform, $assetArch] = $match;

            if ($assetPlatform === $systemPlatform) {
                // TODO: assetArch === systemArch (no arm builds for test server yet)
                return $asset;
            }
        }

        throw new \RuntimeException("Asset for $systemPlatform not found");
    }

    public function download(SystemInfo $systemInfo): void
    {
        $asset = $this->getAsset($systemInfo->platform, $systemInfo->arch);
        $assetUrl = $asset['browser_download_url'];
        $pathToExtractedAsset = $this->downloadAsset($assetUrl);

        $targetPath = getcwd() . DIRECTORY_SEPARATOR . $systemInfo->temporalServerExecutable;
        $this->filesystem->copy($pathToExtractedAsset . DIRECTORY_SEPARATOR . $systemInfo->temporalServerExecutable, $targetPath);
        $this->filesystem->chmod($targetPath, 0755);
        $this->filesystem->remove($pathToExtractedAsset);
    }

    public function check(string $filename): bool
    {
        return $this->filesystem->exists($filename);
    }

    private function downloadAsset(string $assetUrl): string
    {
        $response = $this->httpClient->request('GET', $assetUrl);
        $assetPath = getcwd() . DIRECTORY_SEPARATOR . basename($assetUrl);

        if ($this->filesystem->exists($assetPath)) {
            $this->filesystem->remove($assetPath);
        }
        $this->filesystem->touch($assetPath);
        $this->filesystem->appendToFile($assetPath, $response->getContent());

        $phar = new \PharData($assetPath);
        $extractedPath = getcwd() . DIRECTORY_SEPARATOR . $phar->getFilename();
        if (!$this->filesystem->exists($extractedPath)) {
            $phar->extractTo(getcwd());
        }
        $this->filesystem->remove($phar->getPath());

        return $extractedPath;
    }

    private function getAsset(string $systemPlatform, string $systemArch): array
    {
        $response = $this->httpClient->request('GET', self::LATEST_JAVA_SDK_RELEASE);
        $assets = $response->toArray()['assets'];

        return $this->findAsset($assets, $systemPlatform, $systemArch);
    }
}
