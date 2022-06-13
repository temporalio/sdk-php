<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Spiral\RoadRunner\Console\Environment\Architecture;
use Spiral\RoadRunner\Console\Environment\OperatingSystem;

final class SystemInfo
{
    private const PLATFORM_MAPPINGS = [
        'darwin' => 'macOS',
        'linux' => 'linux',
        'windows' => 'windows',
    ];

    private const ARCHITECTURE_MAPPINGS = [
        'x64' => 'amd64',
        'amd64' => 'amd64',
        'arm64' => 'aarch64'
    ];

    private const TEMPORAL_EXECUTABLE_MAP = [
        'darwin' => './temporal-test-server',
        'linux' => './temporal-test-server',
        'windows' => 'temporal-test-server.exe',
    ];

    private const RR_EXECUTABLE_MAP = [
        'darwin' => './rr',
        'linux' => './rr',
        'windows' => 'rr.exe',
    ];

    public string $arch;
    public string $platform;
    public string $os;
    public string $temporalServerExecutable;
    public string $rrExecutable;

    private function __construct(
        string $arch,
        string $platform,
        string $os,
        string $temporalServerExecutable,
        string $rrExecutable
    ) {
        $this->arch = $arch;
        $this->platform = $platform;
        $this->os = $os;
        $this->temporalServerExecutable = $temporalServerExecutable;
        $this->rrExecutable = $rrExecutable;
    }

    public static function detect(): self
    {
        $os = OperatingSystem::createFromGlobals();

        return new self(
            $os,
            self::PLATFORM_MAPPINGS[$os],
            self::ARCHITECTURE_MAPPINGS[Architecture::createFromGlobals()],
            self::TEMPORAL_EXECUTABLE_MAP[$os],
            self::RR_EXECUTABLE_MAP[$os],
        );
    }
}
