<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

function benchEnv(string $name): string
{
    $value = \getenv($name);
    if ($value === false || $value === '') {
        throw new \RuntimeException("$name is not set, run the benchmark through bench/run.sh or set it");
    }

    return $value;
}

foreach (\glob(__DIR__ . '/src/*.php') as $file) {
    require_once $file;
}
