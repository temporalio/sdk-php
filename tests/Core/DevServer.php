<?php

declare(strict_types=1);

namespace Temporal\Tests\Core;

use Temporal\Testing\Command;
use Temporal\Testing\Environment;

final class DevServer
{
    public const ADDRESS = '127.0.0.1:7477';

    private static ?Environment $environment = null;

    public static function address(): string
    {
        if (self::$environment === null) {
            $environment = Environment::create(new Command(self::ADDRESS));
            $environment->startTemporalServer();
            \register_shutdown_function(static fn() => $environment->stop());
            self::$environment = $environment;
        }

        return self::ADDRESS;
    }
}
