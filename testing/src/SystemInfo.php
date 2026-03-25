<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Spiral\RoadRunner\Console\Environment\Architecture;
use Spiral\RoadRunner\Console\Environment\OperatingSystem;

final class SystemInfo
{
    private const PLATFORM_MAPPINGS = [
        OperatingSystem::OS_DARWIN => 'macOS',
        OperatingSystem::OS_LINUX => 'linux',
        OperatingSystem::OS_WINDOWS => 'windows',
    ];
    private const ARCHITECTURE_MAPPINGS = [
        'x64' => 'amd64',
        'amd64' => 'amd64',
        'arm64' => 'arm64',
    ];
    private const TEMPORAL_EXECUTABLE_MAP = [
        OperatingSystem::OS_DARWIN => './temporal-test-server',
        OperatingSystem::OS_LINUX => './temporal-test-server',
        OperatingSystem::OS_WINDOWS => 'temporal-test-server.exe',
    ];
    private const TEMPORAL_CLI_EXECUTABLE_MAP = [
        OperatingSystem::OS_DARWIN => './temporal',
        OperatingSystem::OS_LINUX => './temporal',
        OperatingSystem::OS_WINDOWS => 'temporal.exe',
    ];
    private const RR_EXECUTABLE_MAP = [
        OperatingSystem::OS_DARWIN => './rr',
        OperatingSystem::OS_LINUX => './rr',
        OperatingSystem::OS_WINDOWS => 'rr.exe',
    ];

    public string $arch;
    public string $platform;
    public string $os;
    public string $temporalServerExecutable;
    public string $temporalCliExecutable;
    public string $rrExecutable;

    private function __construct(
        string $arch,
        string $platform,
        string $os,
        string $temporalServerExecutable,
        string $rrExecutable,
        string $temporalCliExecutable = 'temporal',
    ) {
        $this->arch = $arch;
        $this->platform = $platform;
        $this->os = $os;
        $this->temporalServerExecutable = $temporalServerExecutable;
        $this->temporalCliExecutable = $temporalCliExecutable;
        $this->rrExecutable = $rrExecutable;
    }

    public static function detect(): self
    {
        $os = OperatingSystem::createFromGlobals();
        $architecture = Architecture::createFromGlobals();
        $rrBinary = \getenv('ROADRUNNER_BINARY');

        return new self(
            $os,
            self::PLATFORM_MAPPINGS[$os] ?? self::PLATFORM_MAPPINGS[OperatingSystem::OS_LINUX],
            self::ARCHITECTURE_MAPPINGS[$architecture] ?? self::ARCHITECTURE_MAPPINGS['amd64'],
            self::TEMPORAL_EXECUTABLE_MAP[$os] ?? self::TEMPORAL_EXECUTABLE_MAP[OperatingSystem::OS_LINUX],
            (\is_string($rrBinary) && $rrBinary !== '') ? $rrBinary : (self::RR_EXECUTABLE_MAP[$os] ?? self::RR_EXECUTABLE_MAP[OperatingSystem::OS_LINUX]),
            self::TEMPORAL_CLI_EXECUTABLE_MAP[$os] ?? self::TEMPORAL_CLI_EXECUTABLE_MAP[OperatingSystem::OS_LINUX],
        );
    }
}
