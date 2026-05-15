<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Runtime;

use Temporal\Testing\Command;

final class State
{
    /** @var non-empty-string */
    public string $namespace;

    /** @var non-empty-string */
    public string $address;

    /**
     * @param non-empty-string $rrConfigDir directory holding .rr.yaml + worker.php
     * @param non-empty-string $workDir working directory the RR process is spawned with
     * @param iterable<non-empty-string, non-empty-string> $testCasesDir reserved for compat; not used by the parity launcher
     */
    public function __construct(
        public readonly Command $command,
        public readonly string $rrConfigDir,
        public readonly string $workDir,
        public readonly iterable $testCasesDir = [],
        public readonly int $activityWorkers = 1,
    ) {
        $this->namespace = $command->namespace ?? 'default';
        $this->address = $command->address;
    }
}
