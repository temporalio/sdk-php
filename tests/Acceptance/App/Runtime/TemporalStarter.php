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

        $this->environment->startTemporalServer(parameters: [
            '--search-attribute', 'foo=text',
            '--search-attribute', 'bar=int',
            '--search-attribute', 'testBool=bool',
            '--search-attribute', 'testInt=int',
            '--search-attribute', 'testFloat=double',
            '--search-attribute', 'testString=text',
            '--search-attribute', 'testKeyword=keyword',
            '--search-attribute', 'testKeywordList=keywordList',
            '--search-attribute', 'testDatetime=datetime',
        ]);
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
