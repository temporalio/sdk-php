<?php

declare(strict_types=1);

namespace Temporal\Tests\Core;

use Temporal\Testing\Command;
use Temporal\Testing\Environment;

final class DevServer
{
    private const FREE_PORT_PROBE = 'tcp://127.0.0.1:0';

    private static ?string $address = null;

    public static function address(): string
    {
        if (self::$address === null) {
            $address = self::freeAddress();
            $environment = Environment::create(new Command($address));
            $environment->startTemporalServer(parameters: ['--headless']);
            \register_shutdown_function(static fn() => $environment->stop());
            self::$address = $address;
        }

        return self::$address;
    }

    private static function freeAddress(): string
    {
        $probe = \stream_socket_server(self::FREE_PORT_PROBE);
        if ($probe === false) {
            throw new \RuntimeException('No free local port for the dev server');
        }
        $address = (string) \stream_socket_get_name($probe, false);
        \fclose($probe);

        return $address;
    }
}
