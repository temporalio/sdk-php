<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

foreach (\glob(__DIR__ . '/src/*.php') as $file) {
    require_once $file;
}
