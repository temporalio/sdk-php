<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\Testing\Environment;

final class TemporalStarter
{
    private Environment $environment;
    private bool $started = false;

    public function __construct()
    {
        $this->environment = Environment::create();
        // \register_shutdown_function(fn() => $this->stop());
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->environment->startTemporalServer();
        $this->started = true;
    }

    public function stop(): void
    {
        if (!$this->started) {
            return;
        }

        $this->environment->stop();
        $this->started = false;
    }
}
