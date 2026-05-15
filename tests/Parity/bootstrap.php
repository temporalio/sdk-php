<?php

declare(strict_types=1);

\chdir(\dirname(__DIR__, 2));
require_once 'vendor/autoload.php';

\spl_autoload_register(static function (string $class): void {
    $prefix = 'Temporal\\Tests\\Parity\\';
    if (!\str_starts_with($class, $prefix)) {
        return;
    }

    $relative = \substr($class, \strlen($prefix));
    $path = __DIR__ . DIRECTORY_SEPARATOR . \str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (\is_file($path)) {
        require $path;
    }
});

if (\getenv('PARITY_DEBUG') === '1') {
    \fwrite(\STDERR, "[parity-bootstrap] PSR-4 autoloader for Temporal\\Tests\\Parity\\ registered\n");
}
