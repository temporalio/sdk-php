<?php

declare(strict_types=1);

\chdir(\dirname(__DIR__, 3));
require_once 'vendor/autoload.php';

\spl_autoload_register(static function (string $class): void {
    $prefix = 'Temporal\\Tests\\Parity\\';
    if (!\str_starts_with($class, $prefix)) {
        return;
    }

    $relative = \substr($class, \strlen($prefix));
    $parityDir = \dirname(__DIR__);

    if (\str_starts_with($relative, 'Framework\\')) {
        $path = $parityDir . DIRECTORY_SEPARATOR
              . \str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    } else {
        $path = $parityDir . DIRECTORY_SEPARATOR . 'Scenarios' . DIRECTORY_SEPARATOR
              . \str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    }

    if (\is_file($path)) {
        require $path;
    }
});

if (\getenv('PARITY_DEBUG') === '1') {
    \fwrite(\STDERR, "[parity-bootstrap] PSR-4 autoloader for Temporal\\Tests\\Parity\\ registered (Framework + Scenarios)\n");
}
