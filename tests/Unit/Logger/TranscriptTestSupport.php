<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Logger;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Per-test temp-directory lifecycle for transcript fixtures.
 *
 * Owns: unique $directory under sys_get_temp_dir() (mkdir on setUp, recursive
 * remove on tearDown, swallowed cleanup failures). Use in any PHPUnit TestCase
 * that needs a writable scratch directory; the prefix is derived from the
 * consumer's short class name for human-readable debugging of leftover paths.
 */
trait TranscriptTestSupport
{
    protected string $directory;

    private Filesystem $filesystem;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = $this->makeTempDirectory();
        $this->filesystem->mkdir($this->directory);
    }

    #[\Override]
    protected function tearDown(): void
    {
        try {
            $this->filesystem->remove($this->directory);
        } catch (IOException) {
        }
    }

    private function makeTempDirectory(): string
    {
        $shortName = \strtolower((new \ReflectionClass(static::class))->getShortName());
        return \sys_get_temp_dir()
            . '/' . $shortName
            . '-' . (\getmypid() ?: 0)
            . '-' . \uniqid('', true);
    }
}
